<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionStatus;
use App\Models\AccountPlan;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurrenceTickCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_tick_confirms_due_pending_and_completes_the_horizon(): void
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

        $this->travelTo('2026-03-01');

        $this->artisan('recurrence:tick')->assertExitCode(0);

        $realizedDue = $plan->dailyTransactions()
            ->where('status', TransactionStatus::Realized)
            ->where('date', '<=', '2026-03-01')
            ->count();

        $this->assertSame(2, $realizedDue);

        $this->assertSame(
            TransactionStatus::Pending,
            $plan->dailyTransactions()->where('date', '2026-03-16')->first()->status,
        );

        $this->assertTrue(
            $plan->dailyTransactions()->where('date', '2027-02-16')->exists(),
        );
    }

    public function test_tick_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'recurrence:tick'));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events->first()->getExpression());
    }
}
