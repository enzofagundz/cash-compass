<?php

use App\Enums\RecurrenceFrequency;
use App\Filament\Resources\AccountPlans\Pages\ManageAccountPlans;
use App\Models\AccountPlan;
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
