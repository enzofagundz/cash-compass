<?php

use App\Enums\TransactionType;
use App\Filament\Pages\Thermometer\ThermometerGrid;
use App\Models\DailyForecast;
use App\Models\DailyTransaction;
use App\Models\DayCheckIn;
use App\Models\User;

it('marks the checked in days of the horizon', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);
    DayCheckIn::factory()->create(['user_id' => $user->id, 'date' => '2026-09-10']);

    $months = app(ThermometerGrid::class)->months($user, 2026, 9, 1);
    $days = collect($months[0]['days'])->keyBy('day');

    expect($days[10]['is_checked_in'])->toBeTrue()
        ->and($days[9]['is_checked_in'])->toBeFalse()
        ->and($days[11]['is_checked_in'])->toBeFalse();
});

it('only reads the check ins of the given user', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $other = User::factory()->create();
    $other->saveInitialBalance(['amount' => 0, 'base_date' => '2026-09-01']);
    DayCheckIn::factory()->create(['user_id' => $other->id, 'date' => '2026-09-10']);

    $months = app(ThermometerGrid::class)->months($user, 2026, 9, 1);
    $days = collect($months[0]['days'])->keyBy('day');

    expect($days[10]['is_checked_in'])->toBeFalse();
});

it('labels, formats and colors each day for the grid', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);
    DailyTransaction::create(['date' => '2026-09-02', 'type' => TransactionType::Expense->value, 'amount' => 1600]);

    $days = collect(app(ThermometerGrid::class)->months($user, 2026, 9, 1)[0]['days'])->keyBy('day');

    expect($days[1]['label'])->toBe('1 de setembro de 2026')
        ->and($days[1]['short_date'])->toBe('01/09/2026')
        ->and($days[1]['is_weekend'])->toBeFalse()
        ->and($days[1]['balance'])->toBe('1000.00')
        ->and($days[1]['balance_color'])->toBe('light-yellow')
        ->and($days[2]['balance'])->toBe('-600.00')
        ->and($days[2]['balance_color'])->toBe('dark-red')
        ->and($days[12]['label'])->toBe('12 de setembro de 2026')
        ->and($days[12]['is_weekend'])->toBeTrue();
});

it('flags the filled and the projected cells of each day', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create(['forecast_divisor_days' => 30]);
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);
    DailyForecast::create(['description' => 'Mercado', 'amount' => 300]);

    DailyTransaction::create(['date' => '2026-09-02', 'type' => TransactionType::Income->value, 'amount' => 1000]);
    DailyTransaction::create(['date' => '2026-09-02', 'type' => TransactionType::Expense->value, 'amount' => 200]);
    DailyTransaction::factory()->pending()->create([
        'user_id' => $user->id,
        'date' => '2026-09-20',
        'type' => TransactionType::Expense->value,
        'amount' => 300,
    ]);

    $days = collect(app(ThermometerGrid::class)->months($user, 2026, 9, 1)[0]['days'])->keyBy('day');

    expect($days[1]['filled_types'])->toBe([])
        ->and($days[1]['projected_types'])->toBe([])
        ->and($days[2]['filled_types'])->toBe(['income', 'expense'])
        ->and($days[2]['projected_types'])->toBe([])
        ->and($days[15]['projected_types'])->toBe([])
        ->and($days[16]['filled_types'])->toBe(['daily'])
        ->and($days[16]['projected_types'])->toBe(['daily'])
        ->and($days[20]['filled_types'])->toBe(['expense', 'daily'])
        ->and($days[20]['projected_types'])->toBe(['expense', 'daily']);
});
