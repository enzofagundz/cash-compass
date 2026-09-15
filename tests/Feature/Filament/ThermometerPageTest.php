<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Filament\Pages\ThermometerPage;
use App\Models\DailyTransaction;
use App\Models\User;
use Livewire\Livewire;

it('lets users open the thermometer and forbids admins', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/thermometer')->assertOk();

    $this->actingAs(User::factory()->admin()->create());

    $this->get('/thermometer')->assertForbidden();
});

it('defaults to the current month and switches the grid', function () {
    $this->travelTo('2026-03-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-01-10', 'type' => 'income', 'amount' => 100]);
    DailyTransaction::create(['date' => '2026-02-10', 'type' => 'income', 'amount' => 200]);

    $component = Livewire::test(ThermometerPage::class)
        ->assertSet('data.year', 2026)
        ->assertSet('data.month', 3);

    expect($component->instance()->getTableRecords())->toHaveCount(31);

    $component
        ->set('data.month', 2)
        ->assertSee('200,00')
        ->set('data.month', 1)
        ->assertSee('100,00');
});

it('shows zeros on empty days and the accumulated balance', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-01-02', 'type' => 'income', 'amount' => 1000]);
    DailyTransaction::create(['date' => '2026-01-02', 'type' => 'expense', 'amount' => 200]);

    $rows = collect(Livewire::test(ThermometerPage::class)->instance()->getTableRecords())->keyBy('day');

    expect($rows)->toHaveCount(31)
        ->and($rows[1]['income'])->toBe('0.00')
        ->and($rows[1]['expense'])->toBe('0.00')
        ->and($rows[1]['result'])->toBe('0.00')
        ->and($rows[1]['balance'])->toBe('0.00')
        ->and($rows[3]['balance'])->toBe('800.00');
});

it('shows a separate projection for future days with pending transactions', function () {
    $this->travelTo('2026-02-10');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-02-10', 'type' => 'income', 'amount' => 1000]);
    DailyTransaction::create([
        'date' => '2026-02-16',
        'type' => 'income',
        'amount' => 5000,
        'status' => 'pending',
        'is_recurring' => true,
    ]);

    $component = Livewire::test(ThermometerPage::class)
        ->assertSet('data.month', 2);

    $rows = collect($component->instance()->getTableRecords())->keyBy('day');

    expect($rows[15]['projection'])->toBeNull()
        ->and($rows[16]['projection'])->toBe('6000.00')
        ->and($rows[16]['balance'])->toBe('1000.00');
});

it('creates a manual transaction from the quick create modal', function () {
    $this->travelTo('2026-01-15');
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ThermometerPage::class)
        ->callAction('create', data: [
            'date' => '2026-01-15',
            'type' => TransactionType::Income->value,
            'amount' => 500,
            'description' => 'Extra',
            'status' => TransactionStatus::Realized->value,
        ])
        ->assertHasNoActionErrors();

    expect(DailyTransaction::where('user_id', $user->id)->count())->toBe(1);
});

it('does not show another users movement', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    $other->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);
    DailyTransaction::create(['date' => '2026-01-02', 'type' => 'income', 'amount' => 4321]);

    $this->actingAs($user);

    Livewire::test(ThermometerPage::class)
        ->assertDontSee('4.321,00');
});
