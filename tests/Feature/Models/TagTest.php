<?php

use App\Models\DailyTransaction;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

it('lets users create and retrieve their own tags', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tag = Tag::create(['name' => 'Saúde']);

    expect($tag->exists)->toBeTrue()
        ->and($tag->user_id)->toBe($user->id)
        ->and(Tag::all()->pluck('name')->all())->toBe(['Saúde']);
});

it('only lets users see their own tags', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA);
    Tag::create(['name' => 'Saúde']);

    $this->actingAs($userB);
    Tag::create(['name' => 'Lazer']);

    $this->actingAs($userA);

    expect(Tag::all()->pluck('name')->all())->toBe(['Saúde'])
        ->and(Tag::forUser($userB)->pluck('name')->all())->toBe(['Lazer']);
});

it('rejects a duplicated tag name for the same user', function () {
    $this->actingAs(User::factory()->create());

    Tag::create(['name' => 'Saúde']);

    expect(fn () => Tag::create(['name' => 'Saúde']))->toThrow(UniqueConstraintViolationException::class);
});

it('attaches tags to a transaction', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tag = Tag::create(['name' => 'Saúde']);
    $transaction = DailyTransaction::create([
        'date' => '2026-01-05',
        'type' => 'expense',
        'amount' => 150,
        'description' => 'Psicólogo',
    ]);

    $transaction->tags()->attach($tag->id);

    expect($transaction->refresh()->tags->pluck('name')->all())->toBe(['Saúde']);
});
