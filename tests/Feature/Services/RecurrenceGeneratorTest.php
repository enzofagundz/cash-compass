<?php

namespace Tests\Feature\Services;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionStatus;
use App\Models\AccountPlan;
use App\Models\DailyTransaction;
use App\Models\User;
use App\Services\RecurrenceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurrenceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_monthly_plan_generates_twelve_pending_transactions(): void
    {
        $this->travelTo('2026-01-01');
        $this->actingAs(User::factory()->create());

        $plan = AccountPlan::create([
            'type' => 'income',
            'description' => 'Salário',
            'expected_amount' => 5000,
            'frequency' => RecurrenceFrequency::Monthly,
            'day_of_month' => 16,
            'starts_at' => '2026-01-01',
        ]);

        $transactions = $plan->dailyTransactions()->orderBy('date')->get();

        $this->assertCount(12, $transactions);
        $this->assertSame('2026-01-16', $transactions->first()->date->format('Y-m-d'));
        $this->assertSame('2026-12-16', $transactions->last()->date->format('Y-m-d'));
        $this->assertSame(TransactionStatus::Pending, $transactions->first()->status);
        $this->assertTrue($transactions->first()->is_recurring);
        $this->assertSame('5000.00', $transactions->first()->amount);
    }

    public function test_generating_again_does_not_duplicate_existing_transactions(): void
    {
        $this->travelTo('2026-01-01');
        $this->actingAs(User::factory()->create());

        $plan = AccountPlan::create([
            'type' => 'income',
            'description' => 'Salário',
            'expected_amount' => 5000,
            'frequency' => RecurrenceFrequency::Monthly,
            'day_of_month' => 16,
            'starts_at' => '2026-01-01',
        ]);

        app(RecurrenceGenerator::class)->generate($plan, CarbonImmutable::parse('2026-01-01'));

        $this->assertSame(12, $plan->dailyTransactions()->count());
    }

    public function test_editing_the_plan_updates_pending_but_not_realized_transactions(): void
    {
        $this->travelTo('2026-01-01');
        $this->actingAs(User::factory()->create());

        $plan = AccountPlan::create([
            'type' => 'income',
            'description' => 'Salário',
            'expected_amount' => 5000,
            'frequency' => RecurrenceFrequency::Monthly,
            'day_of_month' => 16,
            'starts_at' => '2026-01-01',
        ]);

        $past = $plan->dailyTransactions()->orderBy('date')->first();
        $past->update(['status' => TransactionStatus::Realized]);

        $this->travelTo('2026-02-01');
        $plan->update(['expected_amount' => 5500]);

        $this->assertSame('5000.00', $past->refresh()->amount);

        $pending = $plan->dailyTransactions()
            ->where('date', '>', '2026-02-01')
            ->orderBy('date')
            ->first();

        $this->assertSame('5500.00', $pending->amount);
        $this->assertSame(TransactionStatus::Pending, $pending->status);
    }

    public function test_deactivating_the_plan_cancels_future_pending_but_keeps_realized(): void
    {
        $this->travelTo('2026-01-01');
        $this->actingAs(User::factory()->create());

        $plan = AccountPlan::create([
            'type' => 'expense',
            'description' => 'Academia',
            'expected_amount' => 120,
            'frequency' => RecurrenceFrequency::Monthly,
            'day_of_month' => 5,
            'starts_at' => '2026-01-01',
        ]);

        $realized = $plan->dailyTransactions()->orderBy('date')->first();
        $realized->update(['status' => TransactionStatus::Realized]);

        $plan->update(['is_active' => false]);

        $this->assertTrue($realized->refresh()->exists);
        $this->assertSame(TransactionStatus::Realized, $realized->status);
        $this->assertSame(0, $plan->dailyTransactions()->where('status', TransactionStatus::Pending)->count());
    }

    public function test_deleting_plan_with_past_realized_transaction_is_blocked(): void
    {
        $this->travelTo('2026-01-01');
        $this->actingAs(User::factory()->create());

        $plan = AccountPlan::create([
            'type' => 'expense',
            'description' => 'Academia',
            'expected_amount' => 120,
            'frequency' => RecurrenceFrequency::Monthly,
            'day_of_month' => 5,
            'starts_at' => '2026-01-01',
        ]);

        $plan->dailyTransactions()->orderBy('date')->first()->update(['status' => TransactionStatus::Realized]);

        $this->travelTo('2026-02-01');

        $this->assertFalse($plan->delete());
        $this->assertTrue($plan->refresh()->exists);
    }

    public function test_deleting_plan_without_past_realized_removes_pending_and_keeps_manual(): void
    {
        $this->travelTo('2026-01-01');
        $user = User::factory()->create();
        $this->actingAs($user);

        $plan = AccountPlan::create([
            'type' => 'expense',
            'description' => 'Academia',
            'expected_amount' => 120,
            'frequency' => RecurrenceFrequency::Monthly,
            'day_of_month' => 5,
            'starts_at' => '2026-01-01',
        ]);

        $manual = DailyTransaction::create([
            'date' => '2026-01-20',
            'type' => 'expense',
            'amount' => 50,
            'account_plan_id' => $plan->id,
        ]);

        $plan->delete();

        $this->assertDatabaseMissing('account_plans', ['id' => $plan->id]);
        $this->assertSame(0, DailyTransaction::where('account_plan_id', $plan->id)->count());
        $this->assertNull($manual->refresh()->account_plan_id);
    }
}
