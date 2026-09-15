<?php

namespace Tests\Unit\Services;

use App\Enums\RecurrenceFrequency;
use App\Services\RecurrenceCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class RecurrenceCalculatorTest extends TestCase
{
    public function test_monthly_occurrences_clamp_to_last_day_of_short_months(): void
    {
        $dates = (new RecurrenceCalculator)->occurrences(
            frequency: RecurrenceFrequency::Monthly,
            startsAt: CarbonImmutable::parse('2026-01-31'),
            dayOfMonth: 31,
            until: CarbonImmutable::parse('2026-04-30'),
        );

        $this->assertSame(
            ['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'],
            array_map(fn (CarbonImmutable $date): string => $date->format('Y-m-d'), $dates),
        );
    }

    public function test_monthly_day_29_falls_back_to_last_day_in_non_leap_february(): void
    {
        $dates = (new RecurrenceCalculator)->occurrences(
            frequency: RecurrenceFrequency::Monthly,
            startsAt: CarbonImmutable::parse('2026-01-29'),
            dayOfMonth: 29,
            until: CarbonImmutable::parse('2026-03-31'),
        );

        $this->assertSame(
            ['2026-01-29', '2026-02-28', '2026-03-29'],
            array_map(fn (CarbonImmutable $date): string => $date->format('Y-m-d'), $dates),
        );
    }

    public function test_weekly_occurrences_follow_the_requested_weekday(): void
    {
        $dates = (new RecurrenceCalculator)->occurrences(
            frequency: RecurrenceFrequency::Weekly,
            startsAt: CarbonImmutable::parse('2026-01-05'),
            dayOfWeek: CarbonImmutable::FRIDAY,
            until: CarbonImmutable::parse('2026-01-31'),
        );

        $this->assertSame(
            ['2026-01-09', '2026-01-16', '2026-01-23', '2026-01-30'],
            array_map(fn (CarbonImmutable $date): string => $date->format('Y-m-d'), $dates),
        );
    }

    public function test_biweekly_occurrences_advance_by_two_weeks(): void
    {
        $dates = (new RecurrenceCalculator)->occurrences(
            frequency: RecurrenceFrequency::Biweekly,
            startsAt: CarbonImmutable::parse('2026-01-05'),
            until: CarbonImmutable::parse('2026-02-28'),
        );

        $this->assertSame(
            ['2026-01-05', '2026-01-19', '2026-02-02', '2026-02-16'],
            array_map(fn (CarbonImmutable $date): string => $date->format('Y-m-d'), $dates),
        );
    }

    public function test_installment_occurrences_stop_after_the_total_count(): void
    {
        $dates = (new RecurrenceCalculator)->occurrences(
            frequency: RecurrenceFrequency::Installment,
            startsAt: CarbonImmutable::parse('2026-01-10'),
            dayOfMonth: 10,
            occurrences: 10,
            until: CarbonImmutable::parse('2027-12-31'),
        );

        $this->assertCount(10, $dates);
        $this->assertSame('2026-01-10', $dates[0]->format('Y-m-d'));
        $this->assertSame('2026-10-10', $dates[9]->format('Y-m-d'));
    }

    public function test_daily_interval_skips_days(): void
    {
        $dates = (new RecurrenceCalculator)->occurrences(
            frequency: RecurrenceFrequency::Daily,
            startsAt: CarbonImmutable::parse('2026-01-01'),
            interval: 5,
            until: CarbonImmutable::parse('2026-01-21'),
        );

        $this->assertSame(
            ['2026-01-01', '2026-01-06', '2026-01-11', '2026-01-16', '2026-01-21'],
            array_map(fn (CarbonImmutable $date): string => $date->format('Y-m-d'), $dates),
        );
    }

    public function test_yearly_occurrences_clamp_leap_day_to_last_day_of_february(): void
    {
        $dates = (new RecurrenceCalculator)->occurrences(
            frequency: RecurrenceFrequency::Yearly,
            startsAt: CarbonImmutable::parse('2024-02-29'),
            until: CarbonImmutable::parse('2027-12-31'),
        );

        $this->assertSame(
            ['2024-02-29', '2025-02-28', '2026-02-28', '2027-02-28'],
            array_map(fn (CarbonImmutable $date): string => $date->format('Y-m-d'), $dates),
        );
    }
}
