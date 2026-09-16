<?php

use App\Models\DayCheckIn;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

it('lets users check in a day', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $checkIn = DayCheckIn::create(['date' => '2026-09-15']);

    expect($checkIn->exists)->toBeTrue()
        ->and($checkIn->user_id)->toBe($user->id)
        ->and(DayCheckIn::where('date', '2026-09-15')->exists())->toBeTrue();
});

it('toggles the check-in of a day', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(DayCheckIn::toggleFor($user, '2026-09-15'))->toBeTrue()
        ->and(DayCheckIn::where('date', '2026-09-15')->exists())->toBeTrue()
        ->and(DayCheckIn::toggleFor($user, '2026-09-15'))->toBeFalse()
        ->and(DayCheckIn::where('date', '2026-09-15')->exists())->toBeFalse();
});

it('only lets users see their own check-ins', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA);
    DayCheckIn::create(['date' => '2026-09-15']);

    $this->actingAs($userB);

    expect(DayCheckIn::all())->toHaveCount(0)
        ->and(DayCheckIn::forUser($userA)->count())->toBe(1);
});

it('rejects a duplicated check-in for the same day', function () {
    $this->actingAs(User::factory()->create());

    DayCheckIn::create(['date' => '2026-09-15']);

    expect(fn () => DayCheckIn::create(['date' => '2026-09-15']))->toThrow(UniqueConstraintViolationException::class);
});
