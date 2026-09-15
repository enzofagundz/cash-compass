<?php

use App\Models\DailyTransaction;
use App\Models\User;

it('rejects a non-positive amount at the model level', function (float $amount) {
    $this->actingAs(User::factory()->create());

    expect(fn () => DailyTransaction::create([
        'date' => '2026-01-01',
        'type' => 'income',
        'amount' => $amount,
    ]))->toThrow(InvalidArgumentException::class);

    expect(DailyTransaction::count())->toBe(0);
})->with([0, -10]);

it('accepts a positive amount', function () {
    $this->actingAs(User::factory()->create());

    $transaction = DailyTransaction::create([
        'date' => '2026-01-01',
        'type' => 'income',
        'amount' => 10,
    ]);

    expect($transaction->exists)->toBeTrue();
});
