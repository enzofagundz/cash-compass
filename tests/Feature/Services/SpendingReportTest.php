<?php

use App\Enums\TagColor;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\DailyTransaction;
use App\Models\Tag;
use App\Models\User;
use App\Services\SpendingReport;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));

    $this->user = User::factory()->create();
    $this->report = app(SpendingReport::class);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function spending(array $attributes = []): DailyTransaction
{
    return DailyTransaction::factory()->for(test()->user)->create([
        'date' => '2026-10-10',
        'type' => TransactionType::Expense,
        'amount' => '100.00',
        'status' => TransactionStatus::Realized,
        ...$attributes,
    ]);
}

it('resolves the current month by default with the previous period of the same duration', function () {
    $period = $this->report->period(null);

    expect($period['preset'])->toBe(SpendingReport::CURRENT_MONTH)
        ->and($period['start']->toDateString())->toBe('2026-10-01')
        ->and($period['end']->toDateString())->toBe('2026-10-31')
        ->and($period['previous_start']->toDateString())->toBe('2026-08-31')
        ->and($period['previous_end']->toDateString())->toBe('2026-09-30')
        ->and($period['label'])->toBe('Outubro de 2026');
});

it('resolves the last month, three months and current year presets', function () {
    $lastMonth = $this->report->period(['preset' => SpendingReport::LAST_MONTH]);
    $threeMonths = $this->report->period(['preset' => SpendingReport::LAST_THREE_MONTHS]);
    $currentYear = $this->report->period(['preset' => SpendingReport::CURRENT_YEAR]);

    expect($lastMonth['start']->toDateString())->toBe('2026-09-01')
        ->and($lastMonth['end']->toDateString())->toBe('2026-09-30')
        ->and($threeMonths['start']->toDateString())->toBe('2026-08-01')
        ->and($threeMonths['end']->toDateString())->toBe('2026-10-31')
        ->and($currentYear['start']->toDateString())->toBe('2026-01-01')
        ->and($currentYear['end']->toDateString())->toBe('2026-12-31');
});

it('accepts an inclusive custom range and rejects an inverted one', function () {
    $custom = $this->report->period([
        'preset' => SpendingReport::CUSTOM,
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-20',
    ]);

    $invalid = $this->report->period([
        'preset' => SpendingReport::CUSTOM,
        'start_date' => '2026-10-20',
        'end_date' => '2026-10-05',
    ]);

    expect($custom['start']->toDateString())->toBe('2026-10-05')
        ->and($custom['end']->toDateString())->toBe('2026-10-20')
        ->and($custom['is_custom'])->toBeTrue()
        ->and($invalid['preset'])->toBe(SpendingReport::CURRENT_MONTH)
        ->and($invalid['start']->toDateString())->toBe('2026-10-01');
});

it('sums only realized spending types in the period', function () {
    spending(['amount' => '100.00']);
    spending(['amount' => '50.00', 'type' => TransactionType::Daily]);
    spending(['amount' => '25.00', 'type' => TransactionType::Card]);

    spending(['amount' => '999.00', 'type' => TransactionType::Income]);
    spending(['amount' => '999.00', 'type' => TransactionType::Savings]);
    spending(['amount' => '999.00', 'status' => TransactionStatus::Pending]);
    spending(['amount' => '999.00', 'status' => TransactionStatus::Skipped]);
    spending(['amount' => '999.00', 'date' => '2026-09-30']);
    spending(['amount' => '999.00', 'date' => '2026-11-01']);

    $summary = $this->report->summary($this->user, $this->report->period(null));

    expect($summary['total'])->toBe('175.00')
        ->and($summary['total_cents'])->toBe(17500);
});

it('includes the first and last day of the period', function () {
    spending(['date' => '2026-10-01', 'amount' => '10.00']);
    spending(['date' => '2026-10-31', 'amount' => '20.00']);

    expect($this->report->summary($this->user, $this->report->period(null))['total'])->toBe('30.00');
});

it('compares against the previous period and handles a zero previous total', function () {
    spending(['date' => '2026-10-10', 'amount' => '150.00']);
    spending(['date' => '2026-09-10', 'amount' => '100.00']);

    $summary = $this->report->summary($this->user, $this->report->period(null));

    expect($summary['previous_total'])->toBe('100.00')
        ->and($summary['variation'])->toBe('50.00')
        ->and($summary['variation_percent'])->toBe(50.0);

    $emptyPrevious = $this->report->summary(
        User::factory()->create(),
        $this->report->period(null),
    );

    expect($emptyPrevious['variation_percent'])->toBeNull()
        ->and($emptyPrevious['variation'])->toBe('0.00');
});

it('calculates the average per active day and leaves it empty without active days', function () {
    spending(['date' => '2026-10-01', 'amount' => '100.00']);
    spending(['date' => '2026-10-01', 'amount' => '50.00']);
    spending(['date' => '2026-10-02', 'amount' => '30.00']);

    $summary = $this->report->summary($this->user, $this->report->period(null));
    $empty = $this->report->summary(User::factory()->create(), $this->report->period(null));

    expect($summary['active_days'])->toBe(2)
        ->and($summary['average_per_active_day'])->toBe('90.00')
        ->and($empty['active_days'])->toBe(0)
        ->and($empty['average_per_active_day'])->toBeNull();
});

it('picks the top tag deterministically and falls back to untagged spending', function () {
    $beta = Tag::factory()->for($this->user)->create(['name' => 'Beta']);
    $alpha = Tag::factory()->for($this->user)->create(['name' => 'Alpha']);

    spending(['amount' => '50.00'])->tags()->attach($beta);
    spending(['amount' => '50.00'])->tags()->attach($alpha);

    expect($this->report->summary($this->user, $this->report->period(null))['top_tag'])
        ->toMatchArray(['name' => 'Alpha', 'total' => '50.00', 'is_untagged' => false]);

    $untaggedUser = User::factory()->create();
    DailyTransaction::factory()->for($untaggedUser)->create([
        'date' => '2026-10-05',
        'amount' => '40.00',
    ]);

    expect($this->report->summary($untaggedUser, $this->report->period(null))['top_tag'])
        ->toMatchArray(['name' => 'Sem tag', 'total' => '40.00', 'is_untagged' => true]);
});

it('groups manual transactions by the hour of creation and ignores plan occurrences', function () {
    spending([
        'date' => '2026-10-10',
        'amount' => '100.00',
        'created_at' => '2026-10-10 08:15:00',
        'is_recurring' => false,
    ]);
    spending([
        'date' => '2026-10-10',
        'amount' => '50.00',
        'created_at' => '2026-10-10 08:45:00',
        'is_recurring' => false,
    ]);
    spending([
        'date' => '2026-10-10',
        'amount' => '999.00',
        'created_at' => '2026-10-10 09:00:00',
        'is_recurring' => true,
    ]);

    $hourly = $this->report->hourly($this->user, $this->report->period(null));

    expect($hourly)->toHaveCount(1)
        ->and($hourly[0])->toMatchArray([
            'hour' => 8,
            'label' => '08h',
            'total' => '150.00',
            'count' => 2,
            'radius' => 24,
        ]);
});

it('keeps the bubble radius between six and twenty-four pixels', function () {
    spending(['created_at' => '2026-10-10 08:00:00', 'amount' => '10.00']);
    spending(['created_at' => '2026-10-10 09:00:00', 'amount' => '10.00']);
    spending(['created_at' => '2026-10-10 09:05:00', 'amount' => '10.00']);
    spending(['created_at' => '2026-10-10 09:10:00', 'amount' => '10.00']);
    spending(['created_at' => '2026-10-10 09:15:00', 'amount' => '10.00']);

    $hourly = collect($this->report->hourly($this->user, $this->report->period(null)));

    expect($hourly->firstWhere('hour', 8)['radius'])->toBe(15)
        ->and($hourly->firstWhere('hour', 9)['radius'])->toBe(24);
});

it('returns seven weekday buckets with zero on days without spending', function () {
    spending(['date' => '2026-10-05', 'amount' => '70.00']);
    spending(['date' => '2026-10-11', 'amount' => '30.00']);

    $weekdays = $this->report->weekdays($this->user, $this->report->period(null));

    expect($weekdays)->toHaveCount(7)
        ->and($weekdays[0])->toMatchArray(['day' => 1, 'label' => 'Segunda', 'total' => '70.00'])
        ->and($weekdays[6])->toMatchArray(['day' => 7, 'label' => 'Domingo', 'total' => '30.00'])
        ->and($weekdays[2]['total'])->toBe('0.00');
});

it('breaks spending down by the three eligible types with percentages', function () {
    spending(['amount' => '100.00']);
    spending(['amount' => '100.00', 'type' => TransactionType::Daily]);
    spending(['amount' => '200.00', 'type' => TransactionType::Card]);
    spending(['amount' => '999.00', 'type' => TransactionType::Savings]);

    $types = collect($this->report->types($this->user, $this->report->period(null)));

    expect($types->pluck('total', 'type')->all())->toBe([
        'expense' => '100.00',
        'daily' => '100.00',
        'card' => '200.00',
    ])->and($types->pluck('percentage', 'type')->all())->toBe([
        'expense' => 25.0,
        'daily' => 25.0,
        'card' => 50.0,
    ]);
});

it('builds a zero-filled monthly evolution and a single point for one month', function () {
    spending(['date' => '2026-08-10', 'amount' => '10.00']);
    spending(['date' => '2026-10-10', 'amount' => '30.00']);

    $monthly = $this->report->monthly($this->user, $this->report->period([
        'preset' => SpendingReport::LAST_THREE_MONTHS,
    ]));

    expect($monthly)->toHaveCount(3)
        ->and($monthly[0])->toMatchArray(['key' => '2026-08', 'total' => '10.00'])
        ->and($monthly[1])->toMatchArray(['key' => '2026-09', 'total' => '0.00'])
        ->and($monthly[2])->toMatchArray(['key' => '2026-10', 'total' => '30.00']);

    expect($this->report->monthly($this->user, $this->report->period(null)))->toHaveCount(1);
});

it('lists the five largest transactions with a deterministic tie-break', function () {
    $older = spending(['date' => '2026-10-01', 'amount' => '100.00', 'description' => 'Older']);
    $newer = spending(['date' => '2026-10-02', 'amount' => '100.00', 'description' => 'Newer']);
    spending(['date' => '2026-10-03', 'amount' => '300.00', 'description' => 'Biggest']);
    spending(['date' => '2026-10-04', 'amount' => '50.00']);
    spending(['date' => '2026-10-05', 'amount' => '25.00']);
    spending(['date' => '2026-10-06', 'amount' => '10.00']);

    $largest = $this->report->largest($this->user, $this->report->period(null));

    expect($largest)->toHaveCount(5)
        ->and($largest[0]['description'])->toBe('Biggest')
        ->and($largest[1]['description'])->toBe('Older')
        ->and($largest[2]['description'])->toBe('Newer')
        ->and($largest[1]['id'])->toBe($older->id)
        ->and($largest[2]['id'])->toBe($newer->id);
});

it('adds the full amount to every linked tag and keeps the untagged row last', function () {
    $food = Tag::factory()->for($this->user)->create(['name' => 'Alimentação', 'color' => TagColor::Green]);
    $work = Tag::factory()->for($this->user)->archived()->create(['name' => 'Trabalho', 'color' => TagColor::Blue]);
    Tag::factory()->for($this->user)->create(['name' => 'Sem gasto']);

    spending(['amount' => '100.00'])->tags()->attach([$food->id, $work->id]);
    spending(['amount' => '50.00'])->tags()->attach($food);
    spending(['amount' => '50.00']);

    $rows = collect($this->report->tags($this->user, $this->report->period(null)));

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toMatchArray([
            'name' => 'Alimentação',
            'total' => '150.00',
            'count' => 2,
            'average' => '75.00',
            'percentage' => 75.0,
            'is_active' => true,
        ])
        ->and($rows[1])->toMatchArray([
            'name' => 'Trabalho',
            'total' => '100.00',
            'count' => 1,
            'percentage' => 50.0,
            'is_active' => false,
        ])
        ->and($rows[2])->toMatchArray([
            'name' => 'Sem tag',
            'total' => '50.00',
            'percentage' => 25.0,
            'is_untagged' => true,
        ])
        ->and($rows->pluck('name')->all())->not->toContain('Sem gasto');
});

it('allows tag percentages to surpass one hundred percent when tags overlap', function () {
    $first = Tag::factory()->for($this->user)->create(['name' => 'Primeira']);
    $second = Tag::factory()->for($this->user)->create(['name' => 'Segunda']);

    spending(['amount' => '100.00'])->tags()->attach([$first->id, $second->id]);

    $rows = collect($this->report->tags($this->user, $this->report->period(null)));

    expect($rows->sum('percentage'))->toBe(200.0)
        ->and($rows->every(fn (array $row): bool => $row['percentage'] === 100.0))->toBeTrue();
});

it('isolates every aggregation by user', function () {
    $other = User::factory()->create();
    $tag = Tag::factory()->for($other)->create(['name' => 'Outro']);

    spending(['amount' => '10.00']);
    DailyTransaction::factory()->for($other)->create([
        'date' => '2026-10-10',
        'amount' => '999.00',
    ])->tags()->attach($tag);

    $period = $this->report->period(null);

    expect($this->report->summary($this->user, $period)['total'])->toBe('10.00')
        ->and($this->report->summary($other, $period)['total'])->toBe('999.00')
        ->and($this->report->tags($this->user, $period))->toHaveCount(1)
        ->and($this->report->tags($this->user, $period)[0]['name'])->toBe('Sem tag');
});

it('returns empty structures when the period has no spending', function () {
    $period = $this->report->period(['preset' => SpendingReport::LAST_MONTH]);

    expect($this->report->hourly($this->user, $period))->toBe([])
        ->and($this->report->largest($this->user, $period))->toBe([])
        ->and($this->report->tags($this->user, $period))->toBe([])
        ->and($this->report->summary($this->user, $period)['total'])->toBe('0.00');
});
