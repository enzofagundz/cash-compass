<?php

use App\Enums\RecurrenceFrequency;
use App\Services\RecurrenceCalculator;
use Carbon\CarbonImmutable;

it('clamps monthly occurrences to the last day of short months', function () {
    $dates = (new RecurrenceCalculator)->occurrences(
        frequency: RecurrenceFrequency::Monthly,
        startsAt: CarbonImmutable::parse('2026-01-31'),
        dayOfMonth: 31,
        until: CarbonImmutable::parse('2026-04-30'),
    );

    expect(array_map(fn (CarbonImmutable $date): string => $date->format('Y-m-d'), $dates))
        ->toBe(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30']);
});

it('falls back to the last day in non-leap february for monthly day 29', function () {
    $dates = (new RecurrenceCalculator)->occurrences(
        frequency: RecurrenceFrequency::Monthly,
        startsAt: CarbonImmutable::parse('2026-01-29'),
        dayOfMonth: 29,
        until: CarbonImmutable::parse('2026-03-31'),
    );

    expect(array_map(fn (CarbonImmutable $date): string => $date->format('Y-m-d'), $dates))
        ->toBe(['2026-01-29', '2026-02-28', '2026-03-29']);
});

it('follows the requested weekday for weekly occurrences', function () {
    $dates = (new RecurrenceCalculator)->occurrences(
        frequency: RecurrenceFrequency::Weekly,
        startsAt: CarbonImmutable::parse('2026-01-05'),
        dayOfWeek: CarbonImmutable::FRIDAY,
        until: CarbonImmutable::parse('2026-01-31'),
    );

    expect(array_map(fn (CarbonImmutable $date): string => $date->format('Y-m-d'), $dates))
        ->toBe(['2026-01-09', '2026-01-16', '2026-01-23', '2026-01-30']);
});

it('advances biweekly occurrences by two weeks', function () {
    $dates = (new RecurrenceCalculator)->occurrences(
        frequency: RecurrenceFrequency::Biweekly,
        startsAt: CarbonImmutable::parse('2026-01-05'),
        until: CarbonImmutable::parse('2026-02-28'),
    );

    expect(array_map(fn (CarbonImmutable $date): string => $date->format('Y-m-d'), $dates))
        ->toBe(['2026-01-05', '2026-01-19', '2026-02-02', '2026-02-16']);
});

it('stops installment occurrences after the total count', function () {
    $dates = (new RecurrenceCalculator)->occurrences(
        frequency: RecurrenceFrequency::Installment,
        startsAt: CarbonImmutable::parse('2026-01-10'),
        dayOfMonth: 10,
        occurrences: 10,
        until: CarbonImmutable::parse('2027-12-31'),
    );

    expect($dates)->toHaveCount(10)
        ->and($dates[0]->format('Y-m-d'))->toBe('2026-01-10')
        ->and($dates[9]->format('Y-m-d'))->toBe('2026-10-10');
});

it('skips days for daily intervals', function () {
    $dates = (new RecurrenceCalculator)->occurrences(
        frequency: RecurrenceFrequency::Daily,
        startsAt: CarbonImmutable::parse('2026-01-01'),
        interval: 5,
        until: CarbonImmutable::parse('2026-01-21'),
    );

    expect(array_map(fn (CarbonImmutable $date): string => $date->format('Y-m-d'), $dates))
        ->toBe(['2026-01-01', '2026-01-06', '2026-01-11', '2026-01-16', '2026-01-21']);
});

it('clamps yearly leap day occurrences to the last day of february', function () {
    $dates = (new RecurrenceCalculator)->occurrences(
        frequency: RecurrenceFrequency::Yearly,
        startsAt: CarbonImmutable::parse('2024-02-29'),
        until: CarbonImmutable::parse('2027-12-31'),
    );

    expect(array_map(fn (CarbonImmutable $date): string => $date->format('Y-m-d'), $dates))
        ->toBe(['2024-02-29', '2025-02-28', '2026-02-28', '2027-02-28']);
});
