<?php

use App\Enums\RecurrenceFrequency;
use App\Enums\TagColor;
use App\Enums\TransactionType;
use App\Filament\Resources\AccountPlans\Pages\ManageAccountPlans;
use App\Models\AccountPlan;
use App\Models\Tag;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('lets users create an account plan from the panel', function () {
    $this->travelTo('2026-01-01');
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageAccountPlans::class)
        ->callAction('create', data: [
            'type' => 'income',
            'description' => 'Salário',
            'expected_amount' => 5000,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'interval' => 1,
            'day_of_month' => 16,
            'starts_at' => '2026-01-01',
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    $plan = AccountPlan::where('description', 'Salário')->firstOrFail();

    expect($plan->dailyTransactions()->count())->toBe(12);
});

it('creates a plan with tags and propagates them to the occurrences', function () {
    $this->travelTo('2026-01-01');
    $this->actingAs(User::factory()->create());

    $tag = Tag::create(['name' => 'Assinaturas']);

    Livewire::test(ManageAccountPlans::class)
        ->callAction('create', data: [
            'type' => TransactionType::Expense->value,
            'description' => 'Streaming',
            'expected_amount' => 50,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'interval' => 1,
            'day_of_month' => 5,
            'starts_at' => '2026-01-01',
            'is_active' => true,
            'tags' => [$tag->id],
        ])
        ->assertHasNoActionErrors();

    $plan = AccountPlan::firstOrFail();

    expect($plan->tags->pluck('name')->all())->toBe(['Assinaturas'])
        ->and($plan->dailyTransactions()->count())->toBe(12)
        ->and($plan->dailyTransactions()->orderBy('date')->first()->tags->pluck('name')->all())->toBe(['Assinaturas']);
});

it('creates a tag with a color from the plan selector', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageAccountPlans::class)
        ->mountAction('create')
        ->callAction(TestAction::make('createOption')->schemaComponent('tags'), data: [
            'name' => 'Academia',
            'color' => TagColor::Purple->value,
        ]);

    expect(Tag::firstOrFail()->color)->toBe(TagColor::Purple);
});

it('rejects an invalid color from the plan selector', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageAccountPlans::class)
        ->mountAction('create')
        ->callAction(TestAction::make('createOption')->schemaComponent('tags'), data: [
            'name' => 'Academia',
            'color' => 'pink',
        ])
        ->assertHasActionErrors(['color']);

    expect(Tag::count())->toBe(0);
});

it('does not attach an archived tag to a new plan', function () {
    $this->actingAs(User::factory()->create());
    $tag = Tag::create(['name' => 'Academia']);
    $tag->archive();

    Livewire::test(ManageAccountPlans::class)
        ->callAction('create', data: [
            'type' => TransactionType::Expense->value,
            'description' => 'Academia',
            'expected_amount' => 150,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'interval' => 1,
            'day_of_month' => 5,
            'starts_at' => '2026-01-01',
            'is_active' => true,
            'tags' => [$tag->id],
        ])
        ->assertHasActionErrors(['tags.0']);

    expect(AccountPlan::count())->toBe(0);
});

it('preserves an archived linked tag when editing another plan field', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tag = Tag::create(['name' => 'Academia']);
    $tag->archive();
    $plan = AccountPlan::factory()->create([
        'user_id' => $user->id,
        'description' => 'Original',
    ]);
    $plan->tags()->attach($tag);

    Livewire::test(ManageAccountPlans::class)
        ->callAction(TestAction::make('edit')->table($plan), data: [
            'type' => $plan->type->value,
            'description' => 'Atualizado',
            'expected_amount' => $plan->expected_amount,
            'frequency' => $plan->frequency->value,
            'interval' => $plan->interval,
            'starts_at' => $plan->starts_at->toDateString(),
            'is_active' => $plan->is_active,
            'tags' => [$tag->id],
        ])
        ->assertHasNoActionErrors();

    expect($plan->refresh()->description)->toBe('Atualizado')
        ->and($plan->tags->pluck('id')->all())->toBe([$tag->id]);
});

it('does not attach another users tag to a plan', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    $foreign = Tag::create(['name' => 'Lazer']);

    $this->actingAs($user);

    Livewire::test(ManageAccountPlans::class)
        ->callAction('create', data: [
            'type' => TransactionType::Expense->value,
            'description' => 'Streaming',
            'expected_amount' => 50,
            'frequency' => RecurrenceFrequency::Monthly->value,
            'interval' => 1,
            'day_of_month' => 5,
            'starts_at' => '2026-01-01',
            'is_active' => true,
            'tags' => [$foreign->id],
        ])
        ->assertHasActionErrors(['tags.0']);

    expect(AccountPlan::count())->toBe(0);
});

it('only lists their own account plans', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    AccountPlan::create([
        'type' => 'expense',
        'description' => 'Plano de outro usuário',
        'expected_amount' => 100,
        'frequency' => RecurrenceFrequency::Monthly,
        'day_of_month' => 5,
        'starts_at' => '2026-01-01',
    ]);

    $this->actingAs($user);

    Livewire::test(ManageAccountPlans::class)
        ->assertDontSee('Plano de outro usuário');
});

it('forbids admins from the account plans panel', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/account-plans')->assertForbidden();
});
