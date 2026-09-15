<?php

namespace Tests\Feature\Filament;

use App\Enums\RecurrenceFrequency;
use App\Filament\Resources\AccountPlans\Pages\ManageAccountPlans;
use App\Models\AccountPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountPlanResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_an_account_plan_from_the_panel(): void
    {
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

        $this->assertSame(12, $plan->dailyTransactions()->count());
    }

    public function test_user_only_lists_their_own_account_plans(): void
    {
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
    }
}
