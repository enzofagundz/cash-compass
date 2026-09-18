<?php

use App\Models\DailyForecast;
use App\Models\User;
use App\Services\DailyForecastCalculator;

it('returns zero when there are no forecast items', function () {
    $user = User::factory()->create();
    $calculator = app(DailyForecastCalculator::class);

    expect($calculator->monthlyTotal($user))->toBe('0.00')
        ->and($calculator->dailyAmount($user))->toBe('0.00')
        ->and($calculator->divisorDays($user))->toBe(30);
});

it('sums the items and divides by the persisted divisor', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    DailyForecast::create(['description' => 'Mercado', 'amount' => 1200]);
    DailyForecast::create(['description' => 'Padaria', 'amount' => 300]);

    $calculator = app(DailyForecastCalculator::class);

    expect($calculator->monthlyTotal($user))->toBe('1500.00')
        ->and($calculator->dailyAmount($user))->toBe('50.00');
});

it('rounds the daily amount to two decimals', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    DailyForecast::create(['description' => 'Mercado', 'amount' => 100]);

    expect(app(DailyForecastCalculator::class)->dailyAmount($user))->toBe('3.33');
});

it('uses the persisted divisor instead of the default', function () {
    $user = User::factory()->create(['forecast_divisor_days' => 15]);
    $this->actingAs($user);

    DailyForecast::create(['description' => 'Mercado', 'amount' => 300]);

    $calculator = app(DailyForecastCalculator::class);

    expect($calculator->divisorDays($user))->toBe(15)
        ->and($calculator->dailyAmount($user))->toBe('20.00');
});

it('falls back to the default divisor when the persisted value is out of range', function (int $divisor) {
    $user = User::factory()->create(['forecast_divisor_days' => $divisor]);
    $this->actingAs($user);

    DailyForecast::create(['description' => 'Mercado', 'amount' => 300]);

    $calculator = app(DailyForecastCalculator::class);

    expect($calculator->divisorDays($user))->toBe(30)
        ->and($calculator->dailyAmount($user))->toBe('10.00');
})->with([0, 32, 200]);

it('does not mix another users forecasts', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    DailyForecast::create(['description' => 'Do outro', 'amount' => 900]);

    $this->actingAs($user);
    DailyForecast::create(['description' => 'Meu', 'amount' => 30]);

    $calculator = app(DailyForecastCalculator::class);

    expect($calculator->monthlyTotal($user))->toBe('30.00')
        ->and($calculator->dailyAmount($user))->toBe('1.00');
});
