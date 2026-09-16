<?php

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionType;
use App\Filament\Resources\AccountPlans\Pages\ManageAccountPlans;
use App\Models\AccountPlan;
use App\Models\Tag;
use App\Models\User;
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
