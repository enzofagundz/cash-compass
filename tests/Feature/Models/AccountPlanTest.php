<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\AccountPlan;
use App\Models\CardItem;
use App\Models\DailyTransaction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo('2026-01-10');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function detailedPlan(User $user, string $startsAt = '2026-01-10'): AccountPlan
{
    return AccountPlan::factory()->for($user)->card()->create([
        'description' => 'Assinaturas',
        'expected_amount' => '100.00',
        'starts_at' => $startsAt,
        'day_of_month' => 10,
    ]);
}

function detailPendingOccurrence(DailyTransaction $occurrence): void
{
    CardItem::factory()->forTransaction($occurrence)->create([
        'description' => 'Mercado',
        'amount' => $occurrence->amount,
    ]);
}

it('details a pending occurrence without touching the plan', function () {
    $plan = detailedPlan($this->user);

    $pending = $plan->dailyTransactions()
        ->where('status', TransactionStatus::Pending)
        ->where('date', '>=', '2026-01-10')
        ->firstOrFail();

    detailPendingOccurrence($pending);

    expect($pending->refresh()->cardItems()->count())->toBe(1)
        ->and($plan->refresh()->description)->toBe('Assinaturas');
});

it('blocks editing a plan whose pending occurrence would be cancelled', function () {
    $plan = detailedPlan($this->user);
    $pending = $plan->dailyTransactions()->where('date', '2026-01-10')->firstOrFail();
    detailPendingOccurrence($pending);

    expect(fn () => $plan->update(['description' => 'Nova descrição']))
        ->toThrow(ValidationException::class, '10/01/2026');

    expect($plan->refresh()->description)->toBe('Assinaturas')
        ->and($pending->refresh()->cardItems()->count())->toBe(1);
});

it('blocks deactivating a plan with a detailed pending occurrence', function () {
    $plan = detailedPlan($this->user);
    detailPendingOccurrence($plan->dailyTransactions()->where('date', '2026-01-10')->firstOrFail());

    expect(fn () => $plan->update(['is_active' => false]))
        ->toThrow(ValidationException::class, 'itens de cartão');

    expect($plan->refresh()->is_active)->toBeTrue();
});

it('blocks deleting a plan with a detailed pending occurrence', function () {
    $plan = detailedPlan($this->user);
    detailPendingOccurrence($plan->dailyTransactions()->where('date', '2026-01-10')->firstOrFail());

    expect(fn () => $plan->delete())->toThrow(ValidationException::class, 'itens de cartão');

    expect(AccountPlan::query()->find($plan->getKey()))->not->toBeNull();
});

it('keeps cancelling and regenerating pending occurrences without items', function () {
    $plan = detailedPlan($this->user);
    $before = $plan->dailyTransactions()->count();

    $plan->update(['description' => 'Nova descrição']);

    expect($plan->refresh()->description)->toBe('Nova descrição')
        ->and($plan->dailyTransactions()->count())->toBe($before)
        ->and($plan->dailyTransactions()->where('status', TransactionStatus::Pending)->count())->toBeGreaterThan(0);
});

it('releases plan editing once the detailed occurrence is realized', function () {
    $plan = detailedPlan($this->user);
    $pending = $plan->dailyTransactions()->where('date', '2026-01-10')->firstOrFail();
    detailPendingOccurrence($pending);

    $pending->update(['status' => TransactionStatus::Realized]);

    $plan->update(['description' => 'Nova descrição']);

    expect($plan->refresh()->description)->toBe('Nova descrição');
});

it('ignores detailed pending occurrences already in the past', function () {
    $plan = AccountPlan::factory()->for($this->user)->card()->create([
        'description' => 'Assinaturas',
        'expected_amount' => '100.00',
        'starts_at' => '2026-01-10',
        'day_of_month' => 10,
    ]);
    $past = DailyTransaction::factory()->for($this->user)->card()->pending()->create([
        'date' => '2025-12-10',
        'amount' => '100.00',
        'account_plan_id' => $plan->getKey(),
    ]);
    detailPendingOccurrence($past);

    $plan->update(['description' => 'Nova descrição']);

    expect($plan->refresh()->description)->toBe('Nova descrição');
});

it('lists every blocked occurrence in the message', function () {
    $plan = detailedPlan($this->user);
    $future = $plan->dailyTransactions()
        ->where('status', TransactionStatus::Pending)
        ->where('date', '>', '2026-01-10')
        ->orderBy('date')
        ->firstOrFail();
    detailPendingOccurrence($future);

    try {
        $plan->update(['description' => 'Nova descrição']);
    } catch (ValidationException $exception) {
        $message = collect($exception->errors())->flatten()->first();

        expect($message)->toContain($future->date->format('d/m/Y'))
            ->and($message)->toContain('Realize-as ou remova o detalhamento');

        return;
    }

    $this->fail('The plan update should have been blocked.');
});

it('keeps the card type of a blocked plan valid', function () {
    $plan = detailedPlan($this->user);
    detailPendingOccurrence($plan->dailyTransactions()->where('date', '2026-01-10')->firstOrFail());

    expect(fn () => $plan->update(['type' => TransactionType::Expense]))
        ->toThrow(ValidationException::class);

    expect($plan->refresh()->type)->toBe(TransactionType::Card);
});
