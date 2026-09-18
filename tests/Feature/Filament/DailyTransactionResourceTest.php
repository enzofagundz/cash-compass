<?php

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Filament\Resources\DailyTransactions\Pages\ManageDailyTransactions;
use App\Models\AccountPlan;
use App\Models\DailyTransaction;
use App\Models\Tag;
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

it('creates a transaction with tags', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);

    $tag = Tag::create(['name' => 'Saúde']);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-05',
            'type' => TransactionType::Expense->value,
            'amount' => 150,
            'description' => 'Psicólogo',
            'status' => TransactionStatus::Realized->value,
            'tags' => [$tag->id],
        ])
        ->assertHasNoActionErrors();

    expect(DailyTransaction::firstOrFail()->tags->pluck('name')->all())->toBe(['Saúde']);
});

it('creates a tag with a color from the transaction selector', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageDailyTransactions::class)
        ->mountAction('create')
        ->callAction(TestAction::make('createOption')->schemaComponent('tags'), data: [
            'name' => 'Academia',
            'color' => 'purple',
        ]);

    expect(Tag::firstOrFail()->name)->toBe('Academia')
        ->and(Tag::firstOrFail()->color->value)->toBe('purple');
});

it('reuses an active tag when quick creation receives an equivalent name', function () {
    $this->actingAs(User::factory()->create());
    $tag = Tag::create(['name' => 'Academia', 'color' => 'blue']);

    Livewire::test(ManageDailyTransactions::class)
        ->mountAction('create')
        ->callAction(TestAction::make('createOption')->schemaComponent('tags'), data: [
            'name' => '  academia ',
            'color' => 'purple',
        ]);

    expect(Tag::count())->toBe(1)
        ->and($tag->refresh()->color->value)->toBe('blue');
});

it('uses the neutral color by default when quickly creating a tag', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageDailyTransactions::class)
        ->mountAction('create')
        ->callAction(TestAction::make('createOption')->schemaComponent('tags'), data: [
            'name' => 'Academia',
        ]);

    expect(Tag::firstOrFail()->color->value)->toBe('neutral');
});

it('rejects quick creation when the equivalent tag is archived', function () {
    $this->actingAs(User::factory()->create());
    $tag = Tag::create(['name' => 'Academia']);
    $tag->archive();

    Livewire::test(ManageDailyTransactions::class)
        ->mountAction('create')
        ->callAction(TestAction::make('createOption')->schemaComponent('tags'), data: [
            'name' => 'academia',
            'color' => 'purple',
        ])
        ->assertHasActionErrors();
});

it('does not attach another users tag to a transaction', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    $foreign = Tag::create(['name' => 'Lazer']);

    $this->actingAs($user);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-05',
            'type' => TransactionType::Expense->value,
            'amount' => 150,
            'status' => TransactionStatus::Realized->value,
            'tags' => [$foreign->id],
        ])
        ->assertHasActionErrors(['tags.0']);

    expect(DailyTransaction::count())->toBe(0);
});

it('shows up to two colored tags and an overflow count in the transaction table', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transaction = DailyTransaction::factory()->create([
        'user_id' => $user->id,
        'description' => 'Compra',
    ]);
    $first = Tag::create(['name' => 'Casa', 'color' => 'blue']);
    $second = Tag::create(['name' => 'Lazer', 'color' => 'purple']);
    $third = Tag::create(['name' => 'Saúde', 'color' => 'teal']);
    $transaction->tags()->attach([$first->id, $second->id, $third->id]);

    Livewire::test(ManageDailyTransactions::class)
        ->assertSee('Casa')
        ->assertSee('Lazer')
        ->assertSee('+1')
        ->assertSeeHtml('fi-color-blue')
        ->assertSeeHtml('fi-color-purple')
        ->assertDontSee('fi-color-teal');
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

it('shows an archived linked tag while editing a transaction', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tag = Tag::create(['name' => 'Academia']);
    $tag->archive();
    $transaction = DailyTransaction::factory()->create(['user_id' => $user->id]);
    $transaction->tags()->attach($tag);

    Livewire::test(ManageDailyTransactions::class)
        ->mountAction(TestAction::make('edit')->table($transaction))
        ->assertMountedActionModalSee('Academia')
        ->assertMountedActionModalSee('arquivada');
});

it('does not attach an archived tag to a new transaction', function () {
    $this->actingAs(User::factory()->create());
    $tag = Tag::create(['name' => 'Academia']);
    $tag->archive();

    Livewire::test(ManageDailyTransactions::class)
        ->callAction('create', data: [
            'date' => '2026-01-05',
            'type' => TransactionType::Expense->value,
            'amount' => 150,
            'status' => TransactionStatus::Realized->value,
            'tags' => [$tag->id],
        ])
        ->assertHasActionErrors(['tags.0']);

    expect(DailyTransaction::count())->toBe(0);
});

it('preserves an archived linked tag when editing another transaction field', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tag = Tag::create(['name' => 'Academia']);
    $tag->archive();
    $transaction = DailyTransaction::factory()->create([
        'user_id' => $user->id,
        'description' => 'Original',
    ]);
    $transaction->tags()->attach($tag);

    Livewire::test(ManageDailyTransactions::class)
        ->callAction(TestAction::make('edit')->table($transaction), data: [
            'date' => $transaction->date->toDateString(),
            'type' => $transaction->type->value,
            'amount' => $transaction->amount,
            'description' => 'Atualizado',
            'status' => $transaction->status->value,
            'tags' => [$tag->id],
        ])
        ->assertHasNoActionErrors();

    expect($transaction->refresh()->description)->toBe('Atualizado')
        ->and($transaction->tags->pluck('id')->all())->toBe([$tag->id]);
});

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
