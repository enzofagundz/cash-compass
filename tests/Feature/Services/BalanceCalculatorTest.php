<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\DailyTransaction;
use App\Models\User;
use App\Services\BalanceCalculator;

it('accumulates the realized balance across days', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Income->value, 'amount' => 1000]);
    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Expense->value, 'amount' => 200]);

    $calculator = app(BalanceCalculator::class);

    expect($calculator->realized($user, '2026-01-02'))->toBe('800.00')
        ->and($calculator->realized($user, '2026-01-03'))->toBe('800.00');
});

it('recalculates every later day when a past transaction is edited', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Income->value, 'amount' => 1000]);
    $expense = DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Expense->value, 'amount' => 200]);

    $calculator = app(BalanceCalculator::class);

    expect($calculator->realized($user, '2026-01-03'))->toBe('800.00');

    $expense->update(['amount' => 500]);

    expect($calculator->realized($user, '2026-01-02'))->toBe('500.00')
        ->and($calculator->realized($user, '2026-01-03'))->toBe('500.00');
});

it('keeps the balance continuous across months and years', function () {
    $this->travelTo('2026-02-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2025-12-01']);

    DailyTransaction::create(['date' => '2025-12-31', 'type' => TransactionType::Income->value, 'amount' => 100]);
    DailyTransaction::create(['date' => '2026-01-01', 'type' => TransactionType::Expense->value, 'amount' => 50]);

    $calculator = app(BalanceCalculator::class);

    expect($calculator->realized($user, '2025-12-31'))->toBe('1100.00')
        ->and($calculator->realized($user, '2026-01-01'))->toBe('1050.00');
});

it('ignores transactions dated before the initial balance base date', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 100, 'base_date' => '2026-01-10']);

    DailyTransaction::create(['date' => '2026-01-05', 'type' => TransactionType::Income->value, 'amount' => 9999]);
    DailyTransaction::create(['date' => '2026-01-10', 'type' => TransactionType::Income->value, 'amount' => 50]);

    expect(app(BalanceCalculator::class)->realized($user, '2026-01-31'))->toBe('150.00');
});

it('only reads the transactions of the given user', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other);
    $other->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);
    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Income->value, 'amount' => 9999]);

    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);
    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Income->value, 'amount' => 100]);

    expect(app(BalanceCalculator::class)->realized($user, '2026-01-31'))->toBe('100.00');
});

it('ignores pending transactions in the realized balance and adds them to the projection', function () {
    $this->travelTo('2026-02-10');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-02-10', 'type' => TransactionType::Income->value, 'amount' => 1000]);
    DailyTransaction::create([
        'date' => '2026-02-16',
        'type' => TransactionType::Income->value,
        'amount' => 5000,
        'status' => TransactionStatus::Pending->value,
        'is_recurring' => true,
    ]);

    $calculator = app(BalanceCalculator::class);

    expect($calculator->realized($user, '2026-02-16'))->toBe('1000.00')
        ->and($calculator->projected($user, '2026-02-16'))->toBe('6000.00')
        ->and($calculator->projected($user, '2026-02-15'))->toBe('1000.00');
});

it('returns a full month grid with zeros on days without movement', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Income->value, 'amount' => 1000]);
    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Expense->value, 'amount' => 200]);

    $grid = app(BalanceCalculator::class)->monthGrid($user, 2026, 1);

    expect($grid)->toHaveCount(31)
        ->and($grid[0]['day'])->toBe(1)
        ->and($grid[0]['income'])->toBe('0.00')
        ->and($grid[0]['expense'])->toBe('0.00')
        ->and($grid[0]['result'])->toBe('0.00')
        ->and($grid[0]['balance'])->toBe('0.00')
        ->and($grid[2]['day'])->toBe(3)
        ->and($grid[2]['balance'])->toBe('800.00');
});

it('keeps 31 rows and carries the balance on days that do not exist', function () {
    $this->travelTo('2026-02-28');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-02-05', 'type' => TransactionType::Income->value, 'amount' => 100]);

    $grid = app(BalanceCalculator::class)->monthGrid($user, 2026, 2);

    expect($grid)->toHaveCount(31)
        ->and($grid[27]['day'])->toBe(28)
        ->and($grid[27]['balance'])->toBe('100.00')
        ->and($grid[28]['day'])->toBe(29)
        ->and($grid[28]['date'])->toBeNull()
        ->and($grid[28]['balance'])->toBe('100.00')
        ->and($grid[30]['day'])->toBe(31)
        ->and($grid[30]['date'])->toBeNull()
        ->and($grid[30]['balance'])->toBe('100.00');
});

it('exposes projection in the grid only once a pending transaction exists', function () {
    $this->travelTo('2026-02-10');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-02-10', 'type' => TransactionType::Income->value, 'amount' => 1000]);
    DailyTransaction::create([
        'date' => '2026-02-16',
        'type' => TransactionType::Income->value,
        'amount' => 5000,
        'status' => TransactionStatus::Pending->value,
        'is_recurring' => true,
    ]);

    $rows = collect(app(BalanceCalculator::class)->monthGrid($user, 2026, 2))->keyBy('day');

    expect($rows[10]['projection'])->toBeNull()
        ->and($rows[15]['projection'])->toBeNull()
        ->and($rows[16]['projection'])->toBe('6000.00')
        ->and($rows[28]['projection'])->toBe('6000.00');
});

it('ignores skipped transactions in the realized and projected balances', function () {
    $this->travelTo('2026-02-10');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-02-10', 'type' => TransactionType::Income->value, 'amount' => 1000]);
    DailyTransaction::create([
        'date' => '2026-02-16',
        'type' => TransactionType::Income->value,
        'amount' => 5000,
        'status' => TransactionStatus::Skipped->value,
        'is_recurring' => true,
    ]);

    $calculator = app(BalanceCalculator::class);

    expect($calculator->realized($user, '2026-02-28'))->toBe('1000.00')
        ->and($calculator->projected($user, '2026-02-28'))->toBe('1000.00');
});

it('keeps the grid balance continuous across the year boundary', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2025-12-01']);

    DailyTransaction::create(['date' => '2025-12-31', 'type' => TransactionType::Income->value, 'amount' => 1000]);

    $december = collect(app(BalanceCalculator::class)->monthGrid($user, 2025, 12))->keyBy('day');
    $january = collect(app(BalanceCalculator::class)->monthGrid($user, 2026, 1))->keyBy('day');

    expect($december[31]['balance'])->toBe('1000.00')
        ->and($january[1]['balance'])->toBe('1000.00');
});

it('shows zero movement and the pending projection on a future month', function () {
    $this->travelTo('2026-01-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-01-01']);

    DailyTransaction::create([
        'date' => '2026-02-20',
        'type' => TransactionType::Income->value,
        'amount' => 5000,
        'status' => TransactionStatus::Pending->value,
        'is_recurring' => true,
    ]);

    $rows = collect(app(BalanceCalculator::class)->monthGrid($user, 2026, 2))->keyBy('day');

    expect($rows[1]['income'])->toBe('0.00')
        ->and($rows[1]['expense'])->toBe('0.00')
        ->and($rows[1]['result'])->toBe('0.00')
        ->and($rows[1]['balance'])->toBe('1000.00')
        ->and($rows[20]['projection'])->toBe('6000.00');
});
