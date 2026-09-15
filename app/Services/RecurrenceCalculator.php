<?php

namespace App\Services;

use App\Enums\RecurrenceFrequency;
use Carbon\CarbonImmutable;

class RecurrenceCalculator
{
    public const DEFAULT_HORIZON_MONTHS = 12;

    /**
     * Calculate every occurrence date between the start date and a boundary.
     *
     * @param  int|null  $dayOfMonth  Day of the month for monthly and yearly rules. Defaults to the start day.
     * @param  int|null  $occurrences  Total number of occurrences for an installment rule.
     * @return array<int, CarbonImmutable>
     */
    public function occurrences(
        RecurrenceFrequency $frequency,
        CarbonImmutable $startsAt,
        ?CarbonImmutable $until = null,
        int $interval = 1,
        ?int $dayOfMonth = null,
        ?int $dayOfWeek = null,
        ?int $occurrences = null,
    ): array {
        $interval = max(1, $interval);
        $until ??= $startsAt->addMonths(self::DEFAULT_HORIZON_MONTHS);

        $dates = [];

        for ($index = 0; $index < 366; $index++) {
            if ($occurrences !== null && $index >= $occurrences) {
                break;
            }

            $date = $this->occurrenceAt($frequency, $startsAt, $index, $interval, $dayOfMonth, $dayOfWeek);

            if ($date->greaterThan($until)) {
                break;
            }

            if ($date->lessThan($startsAt)) {
                continue;
            }

            $dates[] = $date;
        }

        return $dates;
    }

    private function occurrenceAt(
        RecurrenceFrequency $frequency,
        CarbonImmutable $startsAt,
        int $index,
        int $interval,
        ?int $dayOfMonth,
        ?int $dayOfWeek,
    ): CarbonImmutable {
        $step = $index * $interval;

        return match ($frequency) {
            RecurrenceFrequency::Daily => $startsAt->addDays($step),
            RecurrenceFrequency::Weekly => $this->weeklyOccurrence($startsAt, $step, $dayOfWeek, 1),
            RecurrenceFrequency::Biweekly => $this->weeklyOccurrence($startsAt, $step, $dayOfWeek, 2),
            RecurrenceFrequency::Monthly, RecurrenceFrequency::Installment => $this->monthlyOccurrence($startsAt, $step, $dayOfMonth),
            RecurrenceFrequency::Yearly => $this->monthlyOccurrence($startsAt, $step * 12, $dayOfMonth),
        };
    }

    private function weeklyOccurrence(
        CarbonImmutable $startsAt,
        int $step,
        ?int $dayOfWeek,
        int $weekMultiplier,
    ): CarbonImmutable {
        $anchor = $dayOfWeek === null
            ? $startsAt
            : $startsAt->addDays(($dayOfWeek - $startsAt->dayOfWeek + 7) % 7);

        return $anchor->addWeeks($step * $weekMultiplier);
    }

    private function monthlyOccurrence(
        CarbonImmutable $startsAt,
        int $months,
        ?int $dayOfMonth,
    ): CarbonImmutable {
        $date = $startsAt->addMonthsNoOverflow($months);
        $day = $dayOfMonth ?? $startsAt->day;

        return $date->setDay(min($day, $date->daysInMonth));
    }
}
