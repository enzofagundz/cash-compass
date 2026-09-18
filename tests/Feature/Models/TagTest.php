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

it('normalizes names and applies the default color and active state', function () {
    $this->actingAs(User::factory()->create());

    $tag = Tag::create(['name' => '  Saúde   Familiar  ']);

    expect($tag->name)->toBe('Saúde Familiar')
        ->and($tag->normalized_name)->toBe('saúde familiar')
        ->and($tag->color->value)->toBe('neutral')
        ->and($tag->is_active)->toBeTrue();
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

it('rejects equivalent names after whitespace and case normalization', function () {
    $this->actingAs(User::factory()->create());

    Tag::create(['name' => '  Saúde   Familiar ']);

    expect(fn () => Tag::create(['name' => 'saúde familiar']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows the same normalized name for different users', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $this->actingAs($firstUser);
    $firstTag = Tag::create(['name' => 'Saúde']);

    $this->actingAs($secondUser);
    $secondTag = Tag::create(['name' => ' saúde ']);

    expect($firstTag->user_id)->not->toBe($secondTag->user_id)
        ->and($secondTag->normalized_name)->toBe('saúde');
});

it('keeps archived tags reserved and excludes them from active tags', function () {
    $this->actingAs(User::factory()->create());

    $archived = Tag::create(['name' => 'Saúde']);
    $archived->archive();

    expect(Tag::active()->pluck('id')->all())->toBe([])
        ->and(fn () => Tag::create(['name' => ' saúde ']))
        ->toThrow(UniqueConstraintViolationException::class);
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

it('does not delete a tag that is linked to a transaction', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tag = Tag::create(['name' => 'Saúde']);
    $transaction = DailyTransaction::factory()->create(['user_id' => $user->id]);
    $transaction->tags()->attach($tag);

    expect($tag->delete())->toBeFalse()
        ->and($tag->refresh()->exists)->toBeTrue();
});

it('does not delete a tag with a link hidden by the authenticated-user scope', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($owner);
    $tag = Tag::create(['name' => 'Saúde']);

    $this->actingAs($other);
    $transaction = DailyTransaction::factory()->create(['user_id' => $other->id]);
    $transaction->tags()->attach($tag);

    $this->actingAs($owner);

    expect($tag->delete())->toBeFalse()
        ->and($tag->refresh()->exists)->toBeTrue();
});
