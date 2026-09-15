<?php

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Filament\Resources\DailyTransactions\Pages\ManageDailyTransactions;
use App\Models\AccountPlan;
use App\Models\DailyTransaction;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('lets users create a manual transaction from the panel', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-02',
            'type' => TransactionType::Income->value,
            'amount' => 1000,
            'description' => 'Freelance',
            'status' => TransactionStatus::Realized->value,
        ])
        ->assertHasNoActionErrors();

    $transaction = DailyTransaction::firstOrFail();

    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->amount)->toBe('1000.00')
        ->and($transaction->is_recurring)->toBeFalse()
        ->and($transaction->status)->toBe(TransactionStatus::Realized);
});

it('rejects a non-positive amount', function (float $amount) {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-02',
            'type' => TransactionType::Income->value,
            'amount' => $amount,
            'status' => TransactionStatus::Realized->value,
        ])
        ->assertHasActionErrors(['amount']);

    expect(DailyTransaction::count())->toBe(0);
})->with([0, -10]);

it('allows several manual transactions on the same day alongside a generated one', function () {
    $this->travelTo('2026-01-01');
    $user = User::factory()->create();
    $this->actingAs($user);

    AccountPlan::create([
        'type' => TransactionType::Income,
        'description' => 'Salário',
        'expected_amount' => 5000,
        'frequency' => RecurrenceFrequency::Monthly,
        'day_of_month' => 5,
        'starts_at' => '2026-01-01',
    ]);

    foreach ([TransactionType::Income, TransactionType::Expense] as $type) {
        Livewire::test(ManageDailyTransactions::class)
            ->callAction('create', data: [
                'date' => '2026-01-05',
                'type' => $type->value,
                'amount' => 100,
                'status' => TransactionStatus::Realized->value,
            ])
            ->assertHasNoActionErrors();
    }

    $day = DailyTransaction::where('date', '2026-01-05')->get();

    expect($day)->toHaveCount(3)
        ->and($day->where('is_recurring', true))->toHaveCount(1)
        ->and($day->where('is_recurring', false))->toHaveCount(2);
});

it('only lists the transactions of the authenticated user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    $otherTransaction = DailyTransaction::factory()->create(['date' => '2026-01-02']);

    $this->actingAs($user);

    Livewire::test(ManageDailyTransactions::class)
        ->assertCanNotSeeTableRecords([$otherTransaction]);
});

it('confirms a pending transaction early', function () {
    $this->travelTo('2026-01-01');
    $user = User::factory()->create();
    $this->actingAs($user);

    $pending = DailyTransaction::factory()->pending()->create([
        'user_id' => $user->id,
        'date' => '2026-01-10',
    ]);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction(TestAction::make('confirm')->table($pending));

    expect($pending->refresh()->status)->toBe(TransactionStatus::Realized);
});

it('skips a pending transaction without deleting it', function () {
    $this->travelTo('2026-01-01');
    $user = User::factory()->create();
    $this->actingAs($user);

    $pending = DailyTransaction::factory()->pending()->create([
        'user_id' => $user->id,
        'date' => '2026-01-10',
    ]);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction(TestAction::make('skip')->table($pending));

    expect($pending->refresh()->status)->toBe(TransactionStatus::Skipped)
        ->and($pending->exists)->toBeTrue();
});

it('deletes a transaction', function () {
    $this->travelTo('2026-01-01');
    $user = User::factory()->create();
    $this->actingAs($user);

    $realized = DailyTransaction::factory()->create([
        'user_id' => $user->id,
        'date' => '2026-01-10',
    ]);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction(TestAction::make('delete')->table($realized));

    expect(DailyTransaction::find($realized->id))->toBeNull();
});

it('forbids admins from the transactions panel', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/daily-transactions')->assertForbidden();
});
