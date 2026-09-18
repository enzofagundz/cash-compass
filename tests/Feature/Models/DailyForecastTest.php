<?php

use App\Models\DailyForecast;
use App\Models\User;

it('rejects a non-positive amount at the model level', function (float $amount) {
    $this->actingAs(User::factory()->create());

    expect(fn () => DailyForecast::create([
        'description' => 'Mercado',
        'amount' => $amount,
    ]))->toThrow(InvalidArgumentException::class);

    expect(DailyForecast::count())->toBe(0);
})->with([0, -10]);

it('accepts a positive amount and trims the description', function () {
    $this->actingAs(User::factory()->create());

    $forecast = DailyForecast::create([
        'description' => '  Mercado do mês  ',
        'amount' => 10,
    ]);

    expect($forecast->exists)->toBeTrue()
        ->and($forecast->amount)->toBe('10.00')
        ->and($forecast->description)->toBe('Mercado do mês');
});

it('allows duplicate descriptions and keeps the creation order', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $first = DailyForecast::create(['description' => 'Mercado', 'amount' => 100]);
    $second = DailyForecast::create(['description' => 'Mercado', 'amount' => 200]);

    expect(DailyForecast::query()->forUser($user)->orderBy('id')->pluck('id')->all())
        ->toBe([$first->id, $second->id]);
});

it('only lists the forecasts of the authenticated user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    DailyForecast::create(['description' => 'Do outro', 'amount' => 999]);

    $this->actingAs($user);
    DailyForecast::create(['description' => 'Meu', 'amount' => 30]);

    expect(DailyForecast::count())->toBe(1)
        ->and(DailyForecast::firstOrFail()->description)->toBe('Meu');
});

it('defaults the divisor to thirty days', function () {
    $user = User::factory()->create();

    expect($user->refresh()->forecast_divisor_days)->toBe(30);
});
