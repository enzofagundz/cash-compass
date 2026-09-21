<?php

namespace App\Services;

use App\Enums\Month;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\DailyTransaction;
use App\Models\User;
use App\Models\UserInitialBalance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BalanceCalculator
{
    /**
     * Initial balance of each user, memoized for the request.
     *
     * @var array<int, UserInitialBalance|null>
     */
    private array $initialBalances = [];

    /**
     * First day the initial balance counts, memoized for the request.
     *
     * @var array<int, CarbonImmutable|null>
     */
    private array $startDates = [];

    /**
     * Dates with daily movements already resolved, memoized for the request.
     *
     * @var array<string, Collection<int, string>>
     */
    private array $dailyMovementDayRanges = [];

    public function __construct(
        private readonly DailyForecastCalculator $forecastCalculator,
    ) {}

    /**
     * Realized balance up to the given date, clamped to today.
     *
     * Balance_d = initial balance + realized income − realized expenses since the
     * start date, considering only transactions dated up to today. Before the
     * start date the balance is zero.
     */
    public function realized(User $user, CarbonImmutable|string $date): string
    {
        return $this->centsToString($this->realizedCents($user, $this->limitToToday($this->toDate($date))));
    }

    private function realizedCents(User $user, CarbonImmutable $target): int
    {
        $startsAt = $this->startsAt($user);

        if ($startsAt !== null && $target->lessThan($startsAt)) {
            return 0;
        }

        return $this->initialAmountCents($user) + $this->realizedDeltaCents($user, $target);
    }

    /**
     * First day the initial balance counts: its base date, or its creation
     * date when the base date is empty. Null when there is no balance.
     */
    public function startsAt(User $user): ?CarbonImmutable
    {
        $key = (int) $user->getKey();

        if (! array_key_exists($key, $this->startDates)) {
            $this->startDates[$key] = $this->resolveStartsAt($user);
        }

        return $this->startDates[$key];
    }

    private function resolveStartsAt(User $user): ?CarbonImmutable
    {
        $balance = $this->initialBalance($user);

        if ($balance === null) {
            return null;
        }

        if ($balance->base_date !== null) {
            return CarbonImmutable::instance($balance->base_date)->startOfDay();
        }

        return CarbonImmutable::parse($balance->created_at->toDateString())->startOfDay();
    }

    /**
     * Projected balance: today's realized balance plus the pending future
     * transactions and the daily forecast up to the given date. Before the
     * start date the balance is zero.
     */
    public function projected(User $user, CarbonImmutable|string $date): string
    {
        $target = $this->toDate($date);
        $startsAt = $this->startsAt($user);

        if ($startsAt !== null && $target->lessThan($startsAt)) {
            return $this->centsToString(0);
        }

        $today = $this->today();
        $forecastFrom = $this->forecastStart($user);
        $forecastDaily = $this->forecastDailyCents($user);

        $total = $this->realizedCents($user, $today);

        if ($startsAt !== null && $today->lessThan($startsAt)) {
            $total = $this->initialAmountCents($user);
        }

        if ($target->greaterThanOrEqualTo($today)) {
            $total += $this->pendingDeltaCents($user, $target);
        }

        if ($forecastDaily > 0 && $target->greaterThanOrEqualTo($forecastFrom)) {
            $total -= $this->forecastDaysWithoutDailyMovements(
                $this->dailyMovementDays($user, $forecastFrom, $target),
                $forecastFrom,
                $target,
            ) * $forecastDaily;
        }

        return $this->centsToString($total);
    }

    /**
     * Daily forecast that applies to the given date, or null when it does not
     * apply (no items, before the forecast start, or a daily movement of its
     * own on that day).
     */
    public function dailyForecastFor(User $user, CarbonImmutable|string $date): ?string
    {
        $target = $this->toDate($date);
        $forecastDaily = $this->forecastDailyCents($user);

        if ($forecastDaily <= 0 || $target->lessThan($this->forecastStart($user))) {
            return null;
        }

        if ($this->dailyMovementDays($user, $target, $target)->isNotEmpty()) {
            return null;
        }

        return $this->centsToString($forecastDaily);
    }

    /**
     * Build a multi-month horizon grid with per-type day values and a single
     * projected balance column.
     *
     * Realized transactions always count on their date; pending ones only from
     * today onward; skipped ones never count. The daily forecast fills the
     * daily column of future days that have no daily movement of their own,
     * starting tomorrow (or the initial balance start date, when later). It is
     * never turned into a realized movement and is subtracted from the
     * projected balance. Before the start date every day is zeroed; on the
     * start date the initial amount enters before the day movements. The
     * balance is continuous across months, so every day satisfies:
     * balance = previous balance + day columns.
     *
     * @return array<int, array{year: int, month: int, label: string, days: array<int, array{day: int, date: string, income: string, expense: string, daily: string, savings: string, card: string, balance: string, balance_cents: int, is_today: bool, is_future: bool}>, totals: array{income: string, expense: string, daily: string, savings: string, card: string}}>
     */
    public function horizonGrid(User $user, int $year, int $month, int $months): array
    {
        $months = max(1, $months);
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->addMonthsNoOverflow($months - 1)->endOfMonth();
        $today = $this->today();
        $forecastStart = $this->forecastStart($user);
        $forecastDaily = $this->forecastDailyCents($user);
        $balanceStartsAt = $this->startsAt($user);
        $initialAmount = $this->initialAmountCents($user);

        $dailyMovementDays = $forecastDaily > 0
            ? $this->dailyMovementDays($user, $forecastStart, $end)
            : collect();

        if ($balanceStartsAt !== null && ! $start->greaterThan($balanceStartsAt)) {
            $running = 0;
        } else {
            $running = $initialAmount + $this->realizedDeltaCents($user, $start->subDay());
            $running += $this->pendingDeltaCents($user, $start->subDay());

            if ($forecastDaily > 0 && $start->greaterThan($forecastStart)) {
                $running -= $this->forecastDaysWithoutDailyMovements($dailyMovementDays, $forecastStart, $start->subDay()) * $forecastDaily;
            }
        }

        /** @var Collection<string, Collection<int, DailyTransaction>> $realizedByDay */
        $realizedByDay = $this->groupByDay($this->withStart($user, $this->transactions($user)
            ->where('status', TransactionStatus::Realized)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]))
            ->get());

        $pendingFrom = $start->greaterThan($today) ? $start : $today;

        /** @var Collection<string, Collection<int, DailyTransaction>> $pendingByDay */
        $pendingByDay = $this->groupByDay($this->withStart($user, $this->transactions($user)
            ->where('status', TransactionStatus::Pending)
            ->whereBetween('date', [$pendingFrom->toDateString(), $end->toDateString()]))
            ->get());

        $horizon = [];

        for ($offset = 0; $offset < $months; $offset++) {
            $first = $start->addMonthsNoOverflow($offset);
            $last = $first->endOfMonth();
            $days = [];
            $totals = ['income' => 0, 'expense' => 0, 'daily' => 0, 'savings' => 0, 'card' => 0];

            for ($day = 1; $day <= $last->day; $day++) {
                $date = $first->setDay($day);
                $key = $date->toDateString();
                $values = ['income' => 0, 'expense' => 0, 'daily' => 0, 'savings' => 0, 'card' => 0];

                if ($balanceStartsAt !== null && $date->equalTo($balanceStartsAt)) {
                    $running += $initialAmount;
                }

                $realizedOnDay = $realizedByDay->get($key, new Collection);
                $pendingOnDay = $pendingByDay->get($key, new Collection);

                foreach ([$realizedOnDay, $pendingOnDay] as $transactions) {
                    foreach ($transactions as $transaction) {
                        $amount = $this->toCents($transaction->amount);
                        $values[$transaction->type->value] += $amount;
                        $running += $transaction->type->sign() * $amount;
                    }
                }

                $projectsDaily = $forecastDaily > 0
                    && $date->greaterThanOrEqualTo($forecastStart)
                    && ! $dailyMovementDays->contains($key);

                if ($projectsDaily) {
                    $values['daily'] += $forecastDaily;
                    $running -= $forecastDaily;
                }

                foreach ($values as $type => $amount) {
                    $totals[$type] += $amount;
                }

                $pendingTypeValues = $pendingOnDay
                    ->map(fn (DailyTransaction $transaction): string => $transaction->type->value)
                    ->unique();

                $projectionTypeValues = $pendingTypeValues->all();

                if ($projectsDaily) {
                    $projectionTypeValues[] = TransactionType::Daily->value;
                }

                $days[] = [
                    'day' => $day,
                    'date' => $key,
                    'income' => $this->centsToString($values['income']),
                    'expense' => $this->centsToString($values['expense']),
                    'daily' => $this->centsToString($values['daily']),
                    'savings' => $this->centsToString($values['savings']),
                    'card' => $this->centsToString($values['card']),
                    'balance' => $this->centsToString($running),
                    'balance_cents' => $running,
                    'is_today' => $date->equalTo($today),
                    'is_future' => $date->greaterThan($today),
                    'pending_types' => array_values(array_filter(
                        TransactionType::values(),
                        fn (string $value): bool => $pendingTypeValues->contains($value),
                    )),
                    'projected_types' => array_values(array_filter(
                        TransactionType::values(),
                        fn (string $value): bool => in_array($value, $projectionTypeValues, true),
                    )),
                ];
            }

            $horizon[] = [
                'year' => $first->year,
                'month' => $first->month,
                'label' => $this->monthLabel($first),
                'days' => $days,
                'totals' => [
                    'income' => $this->centsToString($totals['income']),
                    'expense' => $this->centsToString($totals['expense']),
                    'daily' => $this->centsToString($totals['daily']),
                    'savings' => $this->centsToString($totals['savings']),
                    'card' => $this->centsToString($totals['card']),
                ],
            ];
        }

        return $horizon;
    }

    /**
     * Sum the signed realized transactions of the user up to the given date, in cents.
     */
    private function realizedDeltaCents(User $user, CarbonImmutable $upTo): int
    {
        return $this->signedTotalCents(
            $this->withStart($user, $this->transactions($user)
                ->where('status', TransactionStatus::Realized)
                ->where('date', '<=', $upTo->toDateString()))
        );
    }

    /**
     * Sum the signed pending transactions of the user from today up to the target, in cents.
     */
    private function pendingDeltaCents(User $user, CarbonImmutable $target): int
    {
        return $this->signedTotalCents(
            $this->withStart($user, $this->transactions($user)
                ->where('status', TransactionStatus::Pending)
                ->whereBetween('date', [$this->today()->toDateString(), $target->toDateString()]))
        );
    }

    /**
     * Signed sum of the amounts matched by the given query, aggregated by the
     * database and returned in cents.
     *
     * @param  Builder<DailyTransaction>  $query
     */
    private function signedTotalCents(Builder $query): int
    {
        $row = $query->selectRaw(
            'COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN type != ? THEN amount ELSE 0 END), 0) as total',
            [TransactionType::Income->value, TransactionType::Income->value],
        )->first();

        return $this->toCents($row->total ?? 0);
    }

    /**
     * First day the daily forecast applies: tomorrow, or the initial balance
     * start date when it is later.
     */
    private function forecastStart(User $user): CarbonImmutable
    {
        $tomorrow = $this->today()->addDay();
        $startsAt = $this->startsAt($user);

        return $startsAt !== null && $startsAt->greaterThan($tomorrow) ? $startsAt : $tomorrow;
    }

    /**
     * Dates with daily movements that count, within the inclusive range.
     *
     * @return Collection<int, string>
     */
    private function dailyMovementDays(User $user, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        if ($to->lessThan($from)) {
            return collect();
        }

        $key = $user->getKey().'|'.$from->toDateString().'|'.$to->toDateString();

        return $this->dailyMovementDayRanges[$key] ??= DailyTransaction::query()
            ->forUser($user)
            ->where('type', TransactionType::Daily)
            ->where('status', '!=', TransactionStatus::Skipped)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')
            ->map(fn (mixed $date): string => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->values();
    }

    /**
     * Days in the inclusive range where the forecast still projects, counting
     * only the daily movements already resolved for the surrounding window.
     *
     * @param  Collection<int, string>  $dailyMovementDays
     */
    private function forecastDaysWithoutDailyMovements(Collection $dailyMovementDays, CarbonImmutable $from, CarbonImmutable $to): int
    {
        if ($to->lessThan($from)) {
            return 0;
        }

        $withDailyMovement = $dailyMovementDays
            ->filter(fn (string $date): bool => $date >= $from->toDateString() && $date <= $to->toDateString())
            ->count();

        return (int) $from->diffInDays($to) + 1 - $withDailyMovement;
    }

    /**
     * @param  Builder<DailyTransaction>  $query
     * @return Builder<DailyTransaction>
     */
    private function withStart(User $user, Builder $query): Builder
    {
        $startsAt = $this->startsAt($user);

        if ($startsAt !== null) {
            $query->where('date', '>=', $startsAt->toDateString());
        }

        return $query;
    }

    /**
     * @return Builder<DailyTransaction>
     */
    private function transactions(User $user): Builder
    {
        return DailyTransaction::query()->forUser($user);
    }

    /**
     * Initial balance of the user, or null when there is none.
     */
    public function initialBalance(User $user): ?UserInitialBalance
    {
        $key = (int) $user->getKey();

        if (! array_key_exists($key, $this->initialBalances)) {
            $this->initialBalances[$key] = UserInitialBalance::query()->forUser($user)->first();
        }

        return $this->initialBalances[$key];
    }

    private function initialAmountCents(User $user): int
    {
        $balance = $this->initialBalance($user);

        return $balance === null ? 0 : $this->toCents($balance->amount);
    }

    /**
     * Daily forecast rate of the user, in cents.
     */
    private function forecastDailyCents(User $user): int
    {
        return $this->toCents($this->forecastCalculator->dailyAmount($user));
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

    /**
     * @param  Collection<int, DailyTransaction>  $transactions
     * @return Collection<string, Collection<int, DailyTransaction>>
     */
    private function groupByDay(Collection $transactions): Collection
    {
        return $transactions->groupBy(
            fn (DailyTransaction $transaction): string => $transaction->date->format('Y-m-d')
        );
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfDay();
    }

    private function toDate(CarbonImmutable|string $date): CarbonImmutable
    {
        return ($date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date))->startOfDay();
    }

    private function limitToToday(CarbonImmutable $date): CarbonImmutable
    {
        $today = $this->today();

        return $date->greaterThan($today) ? $today : $date;
    }

    private function monthLabel(CarbonImmutable $date): string
    {
        return mb_strtolower(Month::from($date->month)->label(), 'UTF-8').' de '.$date->year;
    }
}
