<?php

namespace App\Filament\Pages\Thermometer;

use App\Enums\Month;
use App\Enums\TransactionType;
use App\Models\DayCheckIn;
use App\Models\User;
use App\Services\BalanceCalculator;
use Carbon\CarbonImmutable;

class ThermometerGrid
{
    public function __construct(
        private readonly BalanceCalculator $balanceCalculator,
    ) {}

    /**
     * Multi-month horizon with the data each grid row needs to be drawn.
     *
     * @return array<int, array<string, mixed>>
     */
    public function months(User $user, int $year, int $month, int $months): array
    {
        $horizon = $this->balanceCalculator->horizonGrid($user, $year, $month, $months);

        if ($horizon === []) {
            return [];
        }

        $checkIns = $this->checkInsByDate($user, $horizon);

        foreach ($horizon as $offset => $monthGrid) {
            foreach ($monthGrid['days'] as $index => $day) {
                $horizon[$offset]['days'][$index] = $this->row($day, $checkIns);
            }
        }

        return $horizon;
    }

    /**
     * Long pt-BR label of a day, shared by the grid and the detail panel.
     */
    public function dayLabel(CarbonImmutable $date): string
    {
        return $date->day.' de '.mb_strtolower(Month::from($date->month)->label(), 'UTF-8').' de '.$date->year;
    }

    /**
     * Background range identifier for a daily accumulated balance in cents.
     */
    public function balanceColor(int $cents): string
    {
        if ($cents <= -50000) {
            return 'dark-red';
        }

        if ($cents <= 0) {
            return 'light-red';
        }

        if ($cents <= 100000) {
            return 'light-yellow';
        }

        if ($cents <= 200000) {
            return 'light-green';
        }

        return 'dark-green';
    }

    /**
     * @param  array<string, mixed>  $day
     * @param  array<string, bool>  $checkIns
     * @return array<string, mixed>
     */
    private function row(array $day, array $checkIns): array
    {
        $date = CarbonImmutable::parse($day['date']);

        return [
            ...$day,
            'label' => $this->dayLabel($date),
            'short_date' => $date->format('d/m/Y'),
            'is_weekend' => $date->isWeekend(),
            'is_checked_in' => isset($checkIns[$day['date']]),
            'balance_color' => $this->balanceColor($day['balance_cents']),
            'filled_types' => $this->filledTypes($day),
        ];
    }

    /**
     * Types of a day with a non-zero value, in column order.
     *
     * @param  array<string, mixed>  $day
     * @return array<int, string>
     */
    private function filledTypes(array $day): array
    {
        return array_values(array_filter(
            TransactionType::values(),
            fn (string $value): bool => (float) $day[$value] !== 0.0,
        ));
    }

    /**
     * Checked-in days of the horizon keyed by date.
     *
     * @param  array<int, array<string, mixed>>  $horizon
     * @return array<string, bool>
     */
    private function checkInsByDate(User $user, array $horizon): array
    {
        [$start, $end] = $this->horizonRange($horizon);

        if ($start === null || $end === null) {
            return [];
        }

        return DayCheckIn::query()
            ->forUser($user)
            ->whereBetween('date', [$start, $end])
            ->pluck('date')
            ->mapWithKeys(fn (mixed $date): array => [
                CarbonImmutable::parse($date)->toDateString() => true,
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $horizon
     * @return array{0: string|null, 1: string|null}
     */
    private function horizonRange(array $horizon): array
    {
        $dates = [];

        foreach ($horizon as $month) {
            foreach ($month['days'] as $day) {
                $date = $day['date'];

                if (is_string($date)) {
                    $dates[] = $date;
                }
            }
        }

        if ($dates === []) {
            return [null, null];
        }

        return [min($dates), max($dates)];
    }
}
