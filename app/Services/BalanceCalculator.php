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
    private const GRID_DAYS = 31;

    public function __construct(
        private readonly DailyForecastCalculator $forecastCalculator,
    ) {}

    /**
     * Realized balance up to the given date, clamped to today.
     *
     * Balance_d = initial balance + realized income − realized expenses since the
     * base date, considering only transactions dated up to today.
     */
    public function realized(User $user, CarbonImmutable|string $date): string
    {
        $target = $this->limitToToday($this->toDate($date));

        return $this->format($this->initialAmount($user) + $this->realizedDelta($user, $target));
    }

    /**
     * Projected balance: today's realized balance plus the pending future
     * transactions and the daily forecast up to the given date.
     */
    public function projected(User $user, CarbonImmutable|string $date): string
    {
        $target = $this->toDate($date);
        $today = $this->today();
        $forecastFrom = $this->forecastStart($user);

        $total = (float) $this->realized($user, $today);

        if ($target->greaterThanOrEqualTo($today)) {
            $total += $this->signedDelta($this->pendingUpTo($user, $target));
        }

        if ($target->greaterThanOrEqualTo($forecastFrom)) {
            $days = $this->forecastDays($forecastFrom, $target);
            $total -= $days * (float) $this->forecastCalculator->dailyAmount($user);
        }

        return $this->format($total);
    }

    /**
     * Build the fixed 1–31 day grid for the given month in a single pass.
     *
     * The projection is only exposed on days that have at least one pending
     * transaction up to them, so it is never mixed into the realized balance.
     *
     * @return array<int, array{day: int, date: string|null, income: string, expense: string, result: string, balance: string, projection: string|null}>
     */
    public function monthGrid(User $user, int $year, int $month): array
    {
        $first = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $last = $first->endOfMonth();
        $today = $this->today();

        $running = (float) $this->realized($user, $first->subDay());
        $realizedToday = (float) $this->realized($user, $today);

        /** @var Collection<string, Collection<int, DailyTransaction>> $realizedByDay */
        $realizedByDay = $this->groupByDay($this->realizedWithin($user, $first, $last));

        /** @var Collection<string, Collection<int, DailyTransaction>> $pendingByDay */
        $pendingByDay = $this->groupByDay($this->pendingWithin($user, $first, $last));

        $pendingDelta = 0.0;
        $hasPending = false;

        $grid = [];

        for ($day = 1; $day <= self::GRID_DAYS; $day++) {
            if ($day > $last->day) {
                $grid[] = $this->row($day, null, 0.0, 0.0, $running, null);

                continue;
            }

            $date = $first->setDay($day);
            $key = $date->toDateString();
            $transactions = $realizedByDay->get($key, new Collection);

            $income = $this->absoluteSum($transactions->filter(fn (DailyTransaction $transaction): bool => $transaction->type->isIncome()));
            $expense = $this->absoluteSum($transactions->filter(fn (DailyTransaction $transaction): bool => ! $transaction->type->isIncome()));

            $running += $income - $expense;

            $projection = null;

            if ($date->greaterThan($today)) {
                $pending = $pendingByDay->get($key, new Collection);

                $hasPending = $hasPending || $pending->isNotEmpty();
                $pendingDelta += $this->signedDelta($pending);

                if ($hasPending) {
                    $projection = $this->format($realizedToday + $pendingDelta);
                }
            }

            $grid[] = $this->row($day, $key, $income, $expense, $running, $projection);
        }

        return $grid;
    }

    /**
     * Build a multi-month horizon grid with per-type day values and a single
     * projected balance column.
     *
     * Realized transactions always count on their date; pending ones only from
     * today onward; skipped ones never count. The daily forecast subtracts its
     * daily amount from today onward — or from the initial balance base date,
     * when that date is in the future — and never becomes realized. The balance
     * is continuous across months, so every day satisfies:
     * balance = previous balance + day columns.
     *
     * @return array<int, array{year: int, month: int, label: string, days: array<int, array{day: int, date: string, income: string, expense: string, forecast: string, savings: string, card: string, balance: string, is_today: bool, is_future: bool}>, totals: array{income: string, expense: string, forecast: string, savings: string, card: string}}>
     */
    public function horizonGrid(User $user, int $year, int $month, int $months): array
    {
        $months = max(1, $months);
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->addMonthsNoOverflow($months - 1)->endOfMonth();
        $today = $this->today();
        $forecastFrom = $this->forecastStart($user);
        $forecastDaily = (float) $this->forecastCalculator->dailyAmount($user);

        $running = $this->initialAmount($user) + $this->realizedDelta($user, $start->subDay());
        $running += $this->signedDelta($this->pendingUpTo($user, $start->subDay()));

        if ($start->greaterThan($forecastFrom)) {
            $running -= $this->forecastDays($forecastFrom, $start->subDay()) * $forecastDaily;
        }

        /** @var Collection<string, Collection<int, DailyTransaction>> $realizedByDay */
        $realizedByDay = $this->groupByDay($this->withBaseDate($user, $this->transactions($user)
            ->where('status', TransactionStatus::Realized)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]))
            ->get());

        $pendingFrom = $start->greaterThan($today) ? $start : $today;

        /** @var Collection<string, Collection<int, DailyTransaction>> $pendingByDay */
        $pendingByDay = $this->groupByDay($this->withBaseDate($user, $this->transactions($user)
            ->where('status', TransactionStatus::Pending)
            ->whereBetween('date', [$pendingFrom->toDateString(), $end->toDateString()]))
            ->get());

        $horizon = [];

        for ($offset = 0; $offset < $months; $offset++) {
            $first = $start->addMonthsNoOverflow($offset);
            $last = $first->endOfMonth();
            $days = [];
            $totals = ['income' => 0.0, 'expense' => 0.0, 'forecast' => 0.0, 'savings' => 0.0, 'card' => 0.0];

            for ($day = 1; $day <= $last->day; $day++) {
                $date = $first->setDay($day);
                $key = $date->toDateString();
                $values = ['income' => 0.0, 'expense' => 0.0, 'forecast' => 0.0, 'savings' => 0.0, 'card' => 0.0];

                $realizedOnDay = $realizedByDay->get($key, new Collection);
                $pendingOnDay = $pendingByDay->get($key, new Collection);

                foreach ([$realizedOnDay, $pendingOnDay] as $transactions) {
                    foreach ($transactions as $transaction) {
                        $amount = (float) $transaction->amount;
                        $values[$transaction->type->value] += $amount;
                        $running += $transaction->type->sign() * $amount;
                    }
                }

                $forecast = $date->greaterThanOrEqualTo($forecastFrom) ? $forecastDaily : 0.0;
                $values['forecast'] = $forecast;
                $running -= $forecast;

                foreach ($values as $type => $amount) {
                    $totals[$type] += $amount;
                }

                $pendingTypeValues = $pendingOnDay
                    ->map(fn (DailyTransaction $transaction): string => $transaction->type->value)
                    ->unique();

                $days[] = [
                    'day' => $day,
                    'date' => $key,
                    'income' => $this->format($values['income']),
                    'expense' => $this->format($values['expense']),
                    'forecast' => $this->format($values['forecast']),
                    'savings' => $this->format($values['savings']),
                    'card' => $this->format($values['card']),
                    'balance' => $this->format($running),
                    'is_today' => $date->equalTo($today),
                    'is_future' => $date->greaterThan($today),
                    'pending_types' => array_values(array_filter(
                        TransactionType::values(),
                        fn (string $value): bool => $pendingTypeValues->contains($value),
                    )),
                ];
            }

            $horizon[] = [
                'year' => $first->year,
                'month' => $first->month,
                'label' => $this->monthLabel($first),
                'days' => $days,
                'totals' => [
                    'income' => $this->format($totals['income']),
                    'expense' => $this->format($totals['expense']),
                    'forecast' => $this->format($totals['forecast']),
                    'savings' => $this->format($totals['savings']),
                    'card' => $this->format($totals['card']),
                ],
            ];
        }

        return $horizon;
    }

    /**
     * Sum the signed realized transactions of the user up to the given date.
     */
    private function realizedDelta(User $user, CarbonImmutable $upTo): float
    {
        return $this->signedDelta(
            $this->withBaseDate($user, $this->transactions($user)
                ->where('status', TransactionStatus::Realized)
                ->where('date', '<=', $upTo->toDateString()))
                ->get()
        );
    }

    /**
     * @return Collection<int, DailyTransaction>
     */
    private function realizedWithin(User $user, CarbonImmutable $first, CarbonImmutable $last): Collection
    {
        return $this->withBaseDate($user, $this->transactions($user)
            ->where('status', TransactionStatus::Realized)
            ->where('date', '<=', $this->today()->toDateString())
            ->whereBetween('date', [$first->toDateString(), $last->toDateString()]))
            ->get();
    }

    /**
     * @return Collection<int, DailyTransaction>
     */
    private function pendingWithin(User $user, CarbonImmutable $first, CarbonImmutable $last): Collection
    {
        $from = $first->greaterThan($this->today()) ? $first : $this->today();

        return $this->withBaseDate($user, $this->transactions($user)
            ->where('status', TransactionStatus::Pending)
            ->whereBetween('date', [$from->toDateString(), $last->toDateString()]))
            ->get();
    }

    /**
     * @return Collection<int, DailyTransaction>
     */
    private function pendingUpTo(User $user, CarbonImmutable $target): Collection
    {
        return $this->withBaseDate($user, $this->transactions($user)
            ->where('status', TransactionStatus::Pending)
            ->whereBetween('date', [$this->today()->toDateString(), $target->toDateString()]))
            ->get();
    }

    /**
     * First day the daily forecast applies: the initial balance base date when
     * it is in the future, today otherwise.
     */
    private function forecastStart(User $user): CarbonImmutable
    {
        $today = $this->today();
        $baseDate = $this->baseDate($user);

        return $baseDate !== null && $baseDate->greaterThan($today) ? $baseDate : $today;
    }

    /**
     * Number of calendar days in the inclusive range.
     */
    private function forecastDays(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) $from->diffInDays($to) + 1;
    }

    /**
     * @param  Builder<DailyTransaction>  $query
     * @return Builder<DailyTransaction>
     */
    private function withBaseDate(User $user, Builder $query): Builder
    {
        $baseDate = $this->baseDate($user);

        if ($baseDate !== null) {
            $query->where('date', '>=', $baseDate->toDateString());
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

    private function baseDate(User $user): ?CarbonImmutable
    {
        $balance = $this->initialBalance($user);

        if ($balance === null || $balance->base_date === null) {
            return null;
        }

        return CarbonImmutable::instance($balance->base_date)->startOfDay();
    }

    private function initialBalance(User $user): ?UserInitialBalance
    {
        return UserInitialBalance::query()->forUser($user)->first();
    }

    private function initialAmount(User $user): float
    {
        $balance = $this->initialBalance($user);

        return $balance === null ? 0.0 : (float) $balance->amount;
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

    /**
     * @param  iterable<int, DailyTransaction>  $transactions
     */
    private function signedDelta(iterable $transactions): float
    {
        $total = 0.0;

        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->amount;
            $total += $transaction->type->sign() * $amount;
        }

        return $total;
    }

    /**
     * @param  iterable<int, DailyTransaction>  $transactions
     */
    private function absoluteSum(iterable $transactions): float
    {
        $total = 0.0;

        foreach ($transactions as $transaction) {
            $total += (float) $transaction->amount;
        }

        return $total;
    }

    /**
     * @return array{day: int, date: string|null, income: string, expense: string, result: string, balance: string, projection: string|null}
     */
    private function row(int $day, ?string $date, float $income, float $expense, float $balance, ?string $projection): array
    {
        return [
            'day' => $day,
            'date' => $date,
            'income' => $this->format($income),
            'expense' => $this->format($expense),
            'result' => $this->format($income - $expense),
            'balance' => $this->format($balance),
            'projection' => $projection,
        ];
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

    private function format(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function monthLabel(CarbonImmutable $date): string
    {
        return mb_strtolower(Month::from($date->month)->label(), 'UTF-8').' de '.$date->year;
    }
}
