<?php

use App\Enums\TagColor;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\AccountPlan;
use App\Models\DailyTransaction;
use App\Models\Tag;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('creates a tag with its selected color from the panel', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageTags::class)
        ->callAction('create', data: [
            'name' => 'Lazer',
        ])
        ->assertHasNoActionErrors();

    $tag = Tag::firstOrFail();

    expect($tag->name)->toBe('Lazer')
        ->and($tag->color)->toBe(TagColor::Neutral)
        ->and($tag->is_active)->toBeTrue();
});

it('accepts every color in the controlled palette', function (TagColor $color) {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageTags::class)
        ->callAction('create', data: [
            'name' => $color->value,
            'color' => $color->value,
        ])
        ->assertHasNoActionErrors();

    expect(Tag::firstOrFail()->color)->toBe($color);
})->with(TagColor::cases());

it('rejects a color outside the controlled palette in the catalog', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageTags::class)
        ->callAction('create', data: [
            'name' => 'Lazer',
            'color' => 'pink',
        ])
        ->assertHasActionErrors(['color']);

    expect(Tag::count())->toBe(0);
});

it('updates the name and color for every existing link', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tag = Tag::create(['name' => 'Lazer']);
    $transaction = DailyTransaction::factory()->create(['user_id' => $user->id]);
    $transaction->tags()->attach($tag);

    Livewire::test(ManageTags::class)
        ->callAction(TestAction::make('edit')->table($tag), data: [
            'name' => '  Viagens  ',
            'color' => TagColor::Orange->value,
        ])
        ->assertHasNoActionErrors();

    expect($tag->refresh()->name)->toBe('Viagens')
        ->and($tag->color)->toBe(TagColor::Orange)
        ->and($transaction->refresh()->tags->first()->name)->toBe('Viagens')
        ->and($transaction->tags->first()->color)->toBe(TagColor::Orange);
});

it('shows isolated launch and plan usage counts', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tag = Tag::create(['name' => 'Assinaturas']);
    $transaction = DailyTransaction::factory()->create(['user_id' => $user->id]);
    $transaction->tags()->attach($tag);
    $plan = AccountPlan::factory()->create(['user_id' => $user->id]);
    $plan->tags()->attach($tag);

    Livewire::test(ManageTags::class)
        ->assertTableColumnStateSet('daily_transactions_count', 1, $tag)
        ->assertTableColumnStateSet('account_plans_count', 1, $tag);
});

it('orders tags alphabetically and searches by name', function () {
    $this->actingAs(User::factory()->create());
    $zebra = Tag::create(['name' => 'Zebra']);
    $academia = Tag::create(['name' => 'Academia']);

    $component = Livewire::test(ManageTags::class)
        ->assertCanSeeTableRecords([$academia, $zebra], inOrder: true);

    $component
        ->searchTable('Zebra')
        ->assertCanSeeTableRecords([$zebra])
        ->assertCanNotSeeTableRecords([$academia]);
});

it('escapes tag names in badges', function () {
    $this->actingAs(User::factory()->create());
    Tag::create(['name' => '<script>alert(1)</script>']);

    Livewire::test(ManageTags::class)
        ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->assertDontSeeHtml('<script>alert(1)</script>');
});

it('rejects a normalized duplicate name when editing a tag', function () {
    $this->actingAs(User::factory()->create());
    Tag::create(['name' => 'Academia']);
    $tag = Tag::create(['name' => 'Lazer']);

    Livewire::test(ManageTags::class)
        ->callAction(TestAction::make('edit')->table($tag), data: [
            'name' => ' academia ',
            'color' => TagColor::Blue->value,
        ])
        ->assertHasActionErrors();

    expect($tag->refresh()->name)->toBe('Lazer');
});

it('archives a tag without removing its existing links', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tag = Tag::create(['name' => 'Lazer']);
    $transaction = DailyTransaction::factory()->create(['user_id' => $user->id]);
    $transaction->tags()->attach($tag);

    Livewire::test(ManageTags::class)
        ->callAction(TestAction::make('archive')->table($tag));

    expect($tag->refresh()->is_active)->toBeFalse()
        ->and($transaction->refresh()->tags->pluck('id')->all())->toBe([$tag->id]);
});

it('reactivates an archived tag without changing its identity', function () {
    $this->actingAs(User::factory()->create());
    $tag = Tag::create(['name' => 'Lazer', 'color' => TagColor::Purple]);
    $tag->archive();

    Livewire::test(ManageTags::class)
        ->filterTable('is_active', false)
        ->callAction(TestAction::make('reactivate')->table($tag));

    expect($tag->refresh()->is_active)->toBeTrue()
        ->and($tag->name)->toBe('Lazer')
        ->and($tag->color)->toBe(TagColor::Purple);
});

it('deletes an unused tag from the panel', function () {
    $this->actingAs(User::factory()->create());
    $tag = Tag::create(['name' => 'Temporária']);

    Livewire::test(ManageTags::class)
        ->callAction(TestAction::make('delete')->table($tag));

    expect(Tag::find($tag->id))->toBeNull();
});

it('shows active tags by default and archived tags through the state filter', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $active = Tag::create(['name' => 'Academia']);
    $archived = Tag::factory()->archived()->create(['name' => 'Lazer', 'user_id' => $user->id]);

    $component = Livewire::test(ManageTags::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$archived]);

    $component
        ->filterTable('is_active', false)
        ->assertCanSeeTableRecords([$archived])
        ->assertCanNotSeeTableRecords([$active])
        ->filterTable('is_active', null)
        ->assertCanSeeTableRecords([$active, $archived]);
});

it('registers the tag color palette in the panel styles', function (TagColor $color) {
    $this->actingAs(User::factory()->create());

    $this->get('/tags')
        ->assertOk()
        ->assertSee(".fi-color-{$color->value}", escape: false);
})->with(collect(TagColor::cases())->reject(fn (TagColor $color): bool => $color === TagColor::Neutral));

it('forbids administrators from the tags panel', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/tags')->assertForbidden();
});

it('only lists tags owned by the authenticated user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Tag::factory()->create(['user_id' => $other->id, 'name' => 'Outra pessoa']);

    $this->actingAs($user);

    Livewire::test(ManageTags::class)
        ->assertDontSee('Outra pessoa');
});
