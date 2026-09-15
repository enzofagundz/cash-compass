<?php

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionStatus;
use App\Models\AccountPlan;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;

it('confirms due pending and completes the horizon on tick', function () {
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

    expect($realizedDue)->toBe(2)
        ->and($plan->dailyTransactions()->where('date', '2026-03-16')->first()->status)->toBe(TransactionStatus::Pending)
        ->and($plan->dailyTransactions()->where('date', '2027-02-16')->exists())->toBeTrue();
});

it('schedules the recurrence tick daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'recurrence:tick'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->getExpression())->toBe('0 0 * * *');
});
