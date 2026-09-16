<?php

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionStatus;
use App\Models\AccountPlan;
use App\Models\DailyTransaction;
use App\Models\Tag;
use App\Models\User;
use App\Services\RecurrenceGenerator;
use Carbon\CarbonImmutable;

it('generates twelve pending transactions when creating a monthly plan', function () {
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

    expect($transactions)->toHaveCount(12)
        ->and($transactions->first()->date->format('Y-m-d'))->toBe('2026-01-16')
        ->and($transactions->last()->date->format('Y-m-d'))->toBe('2026-12-16')
        ->and($transactions->first()->status)->toBe(TransactionStatus::Pending)
        ->and($transactions->first()->is_recurring)->toBeTrue()
        ->and($transactions->first()->amount)->toBe('5000.00');
});

it('does not duplicate existing transactions when generating again', function () {
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

    expect($plan->dailyTransactions()->count())->toBe(12);
});

it('updates pending but not realized transactions when editing the plan', function () {
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

    expect($past->refresh()->amount)->toBe('5000.00');

    $pending = $plan->dailyTransactions()
        ->where('date', '>', '2026-02-01')
        ->orderBy('date')
        ->first();

    expect($pending->amount)->toBe('5500.00')
        ->and($pending->status)->toBe(TransactionStatus::Pending);
});

it('cancels future pending but keeps realized transactions when deactivating the plan', function () {
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

    expect($realized->refresh()->exists)->toBeTrue()
        ->and($realized->status)->toBe(TransactionStatus::Realized)
        ->and($plan->dailyTransactions()->where('status', TransactionStatus::Pending)->count())->toBe(0);
});

it('blocks deleting a plan with a past realized transaction', function () {
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

    expect($plan->delete())->toBeFalse()
        ->and($plan->refresh()->exists)->toBeTrue();
});

it('removes pending but keeps manual transactions when deleting a plan without past realized', function () {
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

    expect(DailyTransaction::where('account_plan_id', $plan->id)->count())->toBe(0)
        ->and($manual->refresh()->account_plan_id)->toBeNull();
});

it('keeps linked manual transactions when cancelling the plan schedule', function () {
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
        'is_recurring' => false,
        'status' => TransactionStatus::Pending,
    ]);

    $plan->update(['is_active' => false]);

    $manual->refresh();

    expect($manual->exists)->toBeTrue()
        ->and($manual->account_plan_id)->toBe($plan->id)
        ->and($manual->status)->toBe(TransactionStatus::Pending);
});

it('copies the plan tags to the generated transactions', function () {
    $this->travelTo('2026-01-01');
    $this->actingAs(User::factory()->create());

    $tag = Tag::create(['name' => 'Assinaturas']);

    $plan = AccountPlan::create([
        'type' => 'expense',
        'description' => 'Streaming',
        'expected_amount' => 50,
        'frequency' => RecurrenceFrequency::Monthly,
        'day_of_month' => 5,
        'starts_at' => '2026-01-01',
    ]);
    $plan->tags()->attach($tag->id);

    app(RecurrenceGenerator::class)->generate($plan, CarbonImmutable::parse('2026-01-01'));

    $transactions = $plan->dailyTransactions()->orderBy('date')->get();

    expect($transactions)->toHaveCount(12)
        ->and($transactions->first()->tags->pluck('name')->all())->toBe(['Assinaturas'])
        ->and($transactions->last()->tags->pluck('name')->all())->toBe(['Assinaturas']);
});

it('does not regenerate an occurrence that was skipped', function () {
    $this->travelTo('2026-01-01');
    $this->actingAs(User::factory()->create());

    $plan = AccountPlan::create([
        'type' => 'income',
        'description' => 'Salário',
        'expected_amount' => 5000,
        'frequency' => RecurrenceFrequency::Monthly,
        'day_of_month' => 5,
        'starts_at' => '2026-01-01',
    ]);

    $occurrence = $plan->dailyTransactions()->where('date', '2026-02-05')->firstOrFail();
    $occurrence->update(['status' => TransactionStatus::Skipped]);

    $this->travelTo('2026-02-05');
    $this->artisan('recurrence:tick')->assertSuccessful();

    expect($occurrence->refresh()->status)->toBe(TransactionStatus::Skipped)
        ->and($plan->dailyTransactions()->where('date', '2026-02-05')->count())->toBe(1);
});
