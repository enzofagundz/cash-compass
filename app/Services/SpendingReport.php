<?php

namespace App\Services;

use App\Enums\Month;
use App\Enums\TagColor;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\DailyTransaction;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Canonical aggregation for the spending reports.
 *
 * Spending means a realized transaction of type Expense, Daily or Card dated
 * inside the inclusive period. Income, Savings, pending and skipped
 * transactions stay out. Tag totals add the full amount of a transaction to
 * each linked tag, so they may exceed the period total.
 *
 * @phpstan-type ReportPeriod array{start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable, preset: string, label: string, is_custom: bool}
 * @phpstan-type TagRow array{id: int|null, name: string, color: TagColor|null, is_active: bool, total: string, total_cents: int, count: int, average: string, last_date: string|null, percentage: float, is_untagged: bool}
 */
class SpendingReport
{
    public const CURRENT_MONTH = 'current_month';

    public const LAST_MONTH = 'last_month';

    public const LAST_THREE_MONTHS = 'last_three_months';

    public const CURRENT_YEAR = 'current_year';

    public const CUSTOM = 'custom';

    /**
     * @var array<int, TransactionType>
     */
    public const SPENDING_TYPES = [
        TransactionType::Expense,
        TransactionType::Daily,
        TransactionType::Card,
    ];

    /**
     * @return array<string, string>
     */
    public static function presetOptions(): array
    {
        return [
            self::CURRENT_MONTH => 'Mês atual',
            self::LAST_MONTH => 'Mês passado',
            self::LAST_THREE_MONTHS => 'Últimos 3 meses',
            self::CURRENT_YEAR => 'Ano atual',
            self::CUSTOM => 'Personalizado',
        ];
    }

    /**
     * Resolve the selected preset into an inclusive period and the previous
     * period of the same duration. Invalid custom ranges fall back to the
     * current month; the page reports the validation error separately.
     *
     * @param  array<string, mixed>|null  $filters
     * @return ReportPeriod
     */
    public function period(?array $filters): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $preset = is_array($filters) ? (string) ($filters['preset'] ?? self::CURRENT_MONTH) : self::CURRENT_MONTH;
        $start = $today->startOfMonth();
        $end = $today->endOfMonth();
        $isCustom = false;

        switch ($preset) {
            case self::LAST_MONTH:
                $start = $today->subMonthNoOverflow()->startOfMonth();
                $end = $start->endOfMonth();
                break;
            case self::LAST_THREE_MONTHS:
                $start = $today->subMonthsNoOverflow(2)->startOfMonth();
                $end = $today->endOfMonth();
                break;
            case self::CURRENT_YEAR:
                $start = $today->startOfYear();
                $end = $today->endOfYear();
                break;
            case self::CUSTOM:
                $customStart = $this->parseDate($filters['start_date'] ?? null);
                $customEnd = $this->parseDate($filters['end_date'] ?? null);

                if ($customStart !== null && $customEnd !== null && $customStart->lessThanOrEqualTo($customEnd)) {
                    $start = $customStart;
                    $end = $customEnd;
                    $isCustom = true;
                } else {
                    $preset = self::CURRENT_MONTH;
                }
                break;
            default:
                $preset = self::CURRENT_MONTH;
                break;
        }

        $days = (int) $start->diffInDays($end);
        $previousEnd = $start->subDay();
        $previousStart = $previousEnd->subDays($days);

        return [
            'start' => $start,
            'end' => $end,
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd,
            'preset' => $preset,
            'label' => $this->periodLabel($start, $end, $preset, $isCustom),
            'is_custom' => $isCustom,
        ];
    }

    /**
     * Shared indicators shown above both report tabs.
     *
     * @param  ReportPeriod  $period
     * @return array{total: string, total_cents: int, previous_total: string, previous_total_cents: int, variation: string, variation_cents: int, variation_percent: float|null, active_days: int, average_per_active_day: string|null, top_tag: array{name: string, total: string, color: TagColor|null, is_untagged: bool}|null}
     */
    public function summary(User $user, array $period): array
    {
        $transactions = $this->transactions($user, $period);
        $totalCents = $this->sumCents($transactions);
        $previousCents = $this->totalCents($user, [
            'start' => $period['previous_start'],
            'end' => $period['previous_end'],
        ]);

        $activeDays = $transactions
            ->map(fn (DailyTransaction $transaction): string => $transaction->date->format('Y-m-d'))
            ->unique()
            ->count();

        $variationCents = $totalCents - $previousCents;
        $variationPercent = $previousCents > 0
            ? round(($variationCents / $previousCents) * 100, 1)
            : null;

        $tagRows = $this->aggregateTags($transactions, $totalCents);
        $topTag = collect($tagRows)
            ->first(fn (array $row): bool => ! $row['is_untagged']);

        if ($topTag === null) {
            $topTag = collect($tagRows)->first(fn (array $row): bool => $row['is_untagged'] && $row['total_cents'] > 0);
        }

        return [
            'total' => $this->centsToString($totalCents),
            'total_cents' => $totalCents,
            'previous_total' => $this->centsToString($previousCents),
            'previous_total_cents' => $previousCents,
            'variation' => $this->centsToString($variationCents),
            'variation_cents' => $variationCents,
            'variation_percent' => $variationPercent,
            'active_days' => $activeDays,
            'average_per_active_day' => $activeDays > 0
                ? $this->centsToString((int) round($totalCents / $activeDays))
                : null,
            'top_tag' => $topTag === null ? null : [
                'name' => $topTag['name'],
                'total' => $topTag['total'],
                'color' => $topTag['color'],
                'is_untagged' => $topTag['is_untagged'],
            ],
        ];
    }

    /**
     * Bubble chart points for manual transactions, grouped by the hour of
     * creation in the app timezone. The radius scales the transaction count
     * between 6 and 24 pixels.
     *
     * @param  ReportPeriod  $period
     * @return array<int, array{hour: int, label: string, total: string, count: int, radius: int}>
     */
    public function hourly(User $user, array $period): array
    {
        $groups = [];

        foreach ($this->transactions($user, $period) as $transaction) {
            if ($transaction->is_recurring || $transaction->created_at === null) {
                continue;
            }

            $hour = (int) $transaction->created_at
                ->copy()
                ->timezone(config('app.timezone'))
                ->format('G');

            $groups[$hour] ??= ['count' => 0, 'cents' => 0];
            $groups[$hour]['count']++;
            $groups[$hour]['cents'] += $this->toCents($transaction->amount);
        }

        if ($groups === []) {
            return [];
        }

        ksort($groups);
        $maxCount = 0;

        foreach ($groups as $group) {
            $maxCount = max($maxCount, $group['count']);
        }

        $points = [];

        foreach ($groups as $hour => $group) {
            $points[] = [
                'hour' => $hour,
                'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).'h',
                'total' => $this->centsToString($group['cents']),
                'count' => $group['count'],
                'radius' => (int) round(6 + sqrt($group['count'] / $maxCount) * 18),
            ];
        }

        return $points;
    }

    /**
     * Spending by ISO weekday, Monday through Sunday, with zero-filled days.
     *
     * @param  ReportPeriod  $period
     * @return array<int, array{day: int, label: string, total: string, total_cents: int}>
     */
    public function weekdays(User $user, array $period): array
    {
        $labels = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
        $totals = array_fill_keys(array_keys($labels), 0);

        foreach ($this->transactions($user, $period) as $transaction) {
            $totals[$transaction->date->dayOfWeekIso] += $this->toCents($transaction->amount);
        }

        return array_map(
            fn (int $day): array => [
                'day' => $day,
                'label' => $labels[$day],
                'total' => $this->centsToString($totals[$day]),
                'total_cents' => $totals[$day],
            ],
            array_keys($labels),
        );
    }

    /**
     * Share of each eligible spending type in the period.
     *
     * @param  ReportPeriod  $period
     * @return array<int, array{type: string, label: string, total: string, total_cents: int, percentage: float}>
     */
    public function types(User $user, array $period): array
    {
        $totals = [];

        foreach (self::SPENDING_TYPES as $type) {
            $totals[$type->value] = 0;
        }

        foreach ($this->transactions($user, $period) as $transaction) {
            if (! array_key_exists($transaction->type->value, $totals)) {
                continue;
            }

            $totals[$transaction->type->value] += $this->toCents($transaction->amount);
        }

        $total = array_sum($totals);

        return array_map(
            fn (TransactionType $type): array => [
                'type' => $type->value,
                'label' => $type->label(),
                'total' => $this->centsToString($totals[$type->value]),
                'total_cents' => $totals[$type->value],
                'percentage' => $total > 0 ? round(($totals[$type->value] / $total) * 100, 1) : 0.0,
            ],
            self::SPENDING_TYPES,
        );
    }

    /**
     * One point for each month touched by the period, zero-filled.
     *
     * @param  ReportPeriod  $period
     * @return array<int, array{key: string, label: string, total: string, total_cents: int}>
     */
    public function monthly(User $user, array $period): array
    {
        $totals = [];
        $cursor = $period['start']->startOfMonth();
        $last = $period['end']->startOfMonth();

        while ($cursor->lessThanOrEqualTo($last)) {
            $totals[$cursor->format('Y-m')] = 0;
            $cursor = $cursor->addMonthNoOverflow();
        }

        foreach ($this->transactions($user, $period) as $transaction) {
            $key = $transaction->date->format('Y-m');

            if (array_key_exists($key, $totals)) {
                $totals[$key] += $this->toCents($transaction->amount);
            }
        }

        $rows = [];

        foreach ($totals as $key => $cents) {
            $date = CarbonImmutable::createFromFormat('Y-m', $key)->startOfMonth();
            $rows[] = [
                'key' => $key,
                'label' => Month::from($date->month)->shortLabel().'/'.$date->year,
                'total' => $this->centsToString($cents),
                'total_cents' => $cents,
            ];
        }

        return $rows;
    }

    /**
     * Largest eligible transactions, ordered by amount descending and then by
     * date and id ascending for a deterministic tie-break.
     *
     * @param  ReportPeriod  $period
     * @return array<int, array{id: int, date: string, description: string, tags: array<int, string>, amount: string}>
     */
    public function largest(User $user, array $period, int $limit = 5): array
    {
        $rows = $this->transactions($user, $period)
            ->map(fn (DailyTransaction $transaction): array => [
                'id' => (int) $transaction->getKey(),
                'date' => $transaction->date->format('Y-m-d'),
                'description' => $transaction->description ?? 'Sem descrição',
                'tags' => $transaction->tags
                    ->map(fn (Tag $tag): string => $tag->name)
                    ->values()
                    ->all(),
                'amount' => $this->centsToString($this->toCents($transaction->amount)),
                'amount_cents' => $this->toCents($transaction->amount),
            ])
            ->all();

        usort($rows, fn (array $a, array $b): int => $b['amount_cents'] <=> $a['amount_cents']
            ?: strcmp($a['date'], $b['date'])
            ?: $a['id'] <=> $b['id']);

        return array_map(
            fn (array $row): array => [
                'id' => $row['id'],
                'date' => $row['date'],
                'description' => $row['description'],
                'tags' => $row['tags'],
                'amount' => $row['amount'],
            ],
            array_slice($rows, 0, max(1, $limit)),
        );
    }

    /**
     * Every tag with spending in the period, ordered by total descending, plus
     * the "Sem tag" row at the end when there is untagged spending.
     *
     * @param  ReportPeriod  $period
     * @return array<int, TagRow>
     */
    public function tags(User $user, array $period): array
    {
        $transactions = $this->transactions($user, $period);

        return $this->aggregateTags($transactions, $this->sumCents($transactions));
    }

    /**
     * Total eligible spending in the period, in cents.
     *
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $period
     */
    public function totalCents(User $user, array $period): int
    {
        $total = $this->eligibleQuery($user)
            ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->sum('amount');

        return $this->toCents($total);
    }

    /**
     * @return Builder<DailyTransaction>
     */
    private function eligibleQuery(User $user): Builder
    {
        return DailyTransaction::query()
            ->forUser($user)
            ->whereIn('type', array_map(fn (TransactionType $type): string => $type->value, self::SPENDING_TYPES))
            ->where('status', TransactionStatus::Realized);
    }

    /**
     * @param  ReportPeriod  $period
     * @return Collection<int, DailyTransaction>
     */
    private function transactions(User $user, array $period): Collection
    {
        return $this->eligibleQuery($user)
            ->with('tags')
            ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, DailyTransaction>  $transactions
     */
    private function sumCents(Collection $transactions): int
    {
        return $transactions
            ->sum(fn (DailyTransaction $transaction): int => $this->toCents($transaction->amount));
    }

    /**
     * @param  Collection<int, DailyTransaction>  $transactions
     * @return array<int, TagRow>
     */
    private function aggregateTags(Collection $transactions, int $totalCents): array
    {
        /** @var array<int, array{tag: Tag, cents: int, count: int, last_date: string}> $groups */
        $groups = [];
        $untaggedCents = 0;
        $untaggedCount = 0;
        $untaggedLastDate = null;

        foreach ($transactions as $transaction) {
            $cents = $this->toCents($transaction->amount);
            $date = $transaction->date->format('Y-m-d');

            if ($transaction->tags->isEmpty()) {
                $untaggedCents += $cents;
                $untaggedCount++;
                $untaggedLastDate = $untaggedLastDate === null || $date > $untaggedLastDate ? $date : $untaggedLastDate;

                continue;
            }

            foreach ($transaction->tags as $tag) {
                $id = (int) $tag->getKey();
                $groups[$id] ??= ['tag' => $tag, 'cents' => 0, 'count' => 0, 'last_date' => $date];
                $groups[$id]['cents'] += $cents;
                $groups[$id]['count']++;
                $groups[$id]['last_date'] = $date > $groups[$id]['last_date'] ? $date : $groups[$id]['last_date'];
            }
        }

        $rows = array_map(
            fn (array $group): array => [
                'id' => (int) $group['tag']->getKey(),
                'name' => $group['tag']->name,
                'color' => $group['tag']->color,
                'is_active' => $group['tag']->is_active,
                'total' => $this->centsToString($group['cents']),
                'total_cents' => $group['cents'],
                'count' => $group['count'],
                'average' => $this->centsToString((int) round($group['cents'] / max(1, $group['count']))),
                'last_date' => $group['last_date'],
                'percentage' => $totalCents > 0 ? round(($group['cents'] / $totalCents) * 100, 1) : 0.0,
                'is_untagged' => false,
            ],
            array_values($groups),
        );

        usort($rows, fn (array $a, array $b): int => $b['total_cents'] <=> $a['total_cents']
            ?: strcasecmp($a['name'], $b['name'])
            ?: $a['id'] <=> $b['id']);

        if ($untaggedCents > 0) {
            $rows[] = [
                'id' => null,
                'name' => 'Sem tag',
                'color' => null,
                'is_active' => true,
                'total' => $this->centsToString($untaggedCents),
                'total_cents' => $untaggedCents,
                'count' => $untaggedCount,
                'average' => $this->centsToString((int) round($untaggedCents / max(1, $untaggedCount))),
                'last_date' => $untaggedLastDate,
                'percentage' => $totalCents > 0 ? round(($untaggedCents / $totalCents) * 100, 1) : 0.0,
                'is_untagged' => true,
            ];
        }

        return $rows;
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}/', $value) !== 1) {
            return null;
        }

        return CarbonImmutable::parse(substr($value, 0, 10))->startOfDay();
    }

    private function periodLabel(CarbonImmutable $start, CarbonImmutable $end, string $preset, bool $isCustom): string
    {
        if ($isCustom) {
            return $start->format('d/m/Y').' a '.$end->format('d/m/Y');
        }

        if ($preset === self::CURRENT_YEAR) {
            return (string) $start->year;
        }

        if ($preset === self::LAST_THREE_MONTHS) {
            return Month::from($start->month)->shortLabel().'/'.$start->year.' a '
                .Month::from($end->month)->shortLabel().'/'.$end->year;
        }

        return Month::from($start->month)->label().' de '.$start->year;
    }

    private function toCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function centsToString(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
