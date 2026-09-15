<?php

use App\Models\User;
use App\Models\UserInitialBalance;

it('only lets users see their own initial balance', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA);
    UserInitialBalance::create(['amount' => 5000, 'base_date' => '2026-01-01']);

    $this->actingAs($userB);
    UserInitialBalance::create(['amount' => 9000, 'base_date' => '2026-01-01']);

    $this->actingAs($userA);
    $balances = UserInitialBalance::all();

    expect($balances)->toHaveCount(1)
        ->and($balances->first()->amount)->toBe('5000.00');
});

it('does not let users find another users initial balance', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userB);
    $balance = UserInitialBalance::create(['amount' => 9000, 'base_date' => '2026-01-01']);

    $this->actingAs($userA);

    expect(UserInitialBalance::find($balance->id))->toBeNull();
});
