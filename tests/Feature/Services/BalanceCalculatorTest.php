<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\DailyForecast;
use App\Models\DailyTransaction;
use App\Models\DayCheckIn;
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

it('aggregates savings and card movements into the grid expense', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Income->value, 'amount' => 1000]);
    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Expense->value, 'amount' => 100]);
    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Savings->value, 'amount' => 50]);
    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Card->value, 'amount' => 25]);

    $rows = collect(app(BalanceCalculator::class)->monthGrid($user, 2026, 1))->keyBy('day');

    expect($rows[2]['income'])->toBe('1000.00')
        ->and($rows[2]['expense'])->toBe('175.00')
        ->and($rows[2]['result'])->toBe('825.00')
        ->and($rows[2]['balance'])->toBe('825.00');
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

it('subtracts savings and card movements like expenses', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Income->value, 'amount' => 1000]);
    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Expense->value, 'amount' => 200]);
    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Savings->value, 'amount' => 50]);
    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Card->value, 'amount' => 25]);

    expect(app(BalanceCalculator::class)->realized($user, '2026-01-02'))->toBe('725.00');
});

it('builds a twelve month horizon with real days and a continuous balance', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);

    DailyTransaction::create(['date' => '2026-09-16', 'type' => TransactionType::Income->value, 'amount' => 500]);

    $horizon = app(BalanceCalculator::class)->horizonGrid($user, 2026, 9, 12);

    expect($horizon)->toHaveCount(12)
        ->and($horizon[0]['year'])->toBe(2026)
        ->and($horizon[0]['month'])->toBe(9)
        ->and($horizon[0]['label'])->toBe('setembro de 2026')
        ->and($horizon[0]['days'])->toHaveCount(30)
        ->and($horizon[1]['year'])->toBe(2026)
        ->and($horizon[1]['month'])->toBe(10)
        ->and($horizon[5]['month'])->toBe(2)
        ->and($horizon[5]['days'])->toHaveCount(28)
        ->and($horizon[11]['year'])->toBe(2027)
        ->and($horizon[11]['month'])->toBe(8)
        ->and($horizon[0]['days'][14]['day'])->toBe(15)
        ->and($horizon[0]['days'][14]['balance'])->toBe('1000.00')
        ->and($horizon[0]['days'][15]['income'])->toBe('500.00')
        ->and($horizon[0]['days'][15]['balance'])->toBe('1500.00')
        ->and($horizon[1]['days'][0]['balance'])->toBe('1500.00');
});

it('projects future pending movements and ignores skipped ones in the horizon', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);

    DailyTransaction::create(['date' => '2026-09-15', 'type' => TransactionType::Income->value, 'amount' => 200]);
    DailyTransaction::create([
        'date' => '2026-09-16',
        'type' => TransactionType::Expense->value,
        'amount' => 50,
        'status' => TransactionStatus::Pending->value,
        'is_recurring' => true,
    ]);
    DailyTransaction::create([
        'date' => '2026-09-17',
        'type' => TransactionType::Expense->value,
        'amount' => 999,
        'status' => TransactionStatus::Skipped->value,
        'is_recurring' => true,
    ]);
    DailyTransaction::create([
        'date' => '2026-09-10',
        'type' => TransactionType::Expense->value,
        'amount' => 999,
        'status' => TransactionStatus::Pending->value,
        'is_recurring' => true,
    ]);

    $days = collect(app(BalanceCalculator::class)->horizonGrid($user, 2026, 9, 1)[0]['days'])->keyBy('day');

    expect($days[10]['expense'])->toBe('0.00')
        ->and($days[10]['balance'])->toBe('1000.00')
        ->and($days[15]['income'])->toBe('200.00')
        ->and($days[15]['balance'])->toBe('1200.00')
        ->and($days[16]['expense'])->toBe('50.00')
        ->and($days[16]['forecast'])->toBe('0.00')
        ->and($days[16]['balance'])->toBe('1150.00')
        ->and($days[17]['expense'])->toBe('0.00')
        ->and($days[17]['balance'])->toBe('1150.00');
});

it('carries pending movements before a future horizon start into its opening balance', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);

    DailyTransaction::create(['date' => '2026-09-20', 'type' => TransactionType::Expense->value, 'amount' => 100]);
    DailyTransaction::create([
        'date' => '2026-10-05',
        'type' => TransactionType::Expense->value,
        'amount' => 50,
        'status' => TransactionStatus::Pending->value,
        'is_recurring' => true,
    ]);

    $november = app(BalanceCalculator::class)->horizonGrid($user, 2026, 11, 1)[0];

    expect($november['days'][0]['balance'])->toBe('850.00');
});

it('keeps the same day balance across overlapping horizons', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);

    DailyTransaction::create(['date' => '2026-09-20', 'type' => TransactionType::Expense->value, 'amount' => 100]);
    DailyTransaction::create([
        'date' => '2026-10-05',
        'type' => TransactionType::Expense->value,
        'amount' => 50,
        'status' => TransactionStatus::Pending->value,
        'is_recurring' => true,
    ]);

    $calculator = app(BalanceCalculator::class);
    $full = $calculator->horizonGrid($user, 2026, 9, 12);
    $late = $calculator->horizonGrid($user, 2026, 11, 3);

    expect($late[0]['days'][0]['balance'])->toBe($full[2]['days'][0]['balance'])
        ->and($late[0]['days'][0]['balance'])->toBe('850.00');
});

it('marks the day types that include pending movements in the horizon', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);

    DailyTransaction::create(['date' => '2026-09-16', 'type' => TransactionType::Income->value, 'amount' => 500]);
    DailyTransaction::create([
        'date' => '2026-09-16',
        'type' => TransactionType::Card->value,
        'amount' => 50,
        'status' => TransactionStatus::Pending->value,
        'is_recurring' => true,
    ]);
    DailyTransaction::create(['date' => '2026-09-17', 'type' => TransactionType::Expense->value, 'amount' => 20]);
    DailyTransaction::create([
        'date' => '2026-09-17',
        'type' => TransactionType::Expense->value,
        'amount' => 100,
        'status' => TransactionStatus::Pending->value,
        'is_recurring' => true,
    ]);

    $days = collect(app(BalanceCalculator::class)->horizonGrid($user, 2026, 9, 1)[0]['days'])->keyBy('day');

    expect($days[15]['pending_types'])->toBe([])
        ->and($days[16]['pending_types'])->toBe(['card'])
        ->and($days[17]['pending_types'])->toBe(['expense'])
        ->and($days[17]['expense'])->toBe('120.00');
});

it('totals every movement type per month in the horizon', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-09-01']);

    DailyTransaction::create(['date' => '2026-09-05', 'type' => TransactionType::Expense->value, 'amount' => 150]);
    DailyTransaction::create(['date' => '2026-09-05', 'type' => TransactionType::Expense->value, 'amount' => 9.90]);
    DailyTransaction::create(['date' => '2026-09-12', 'type' => TransactionType::Card->value, 'amount' => 19.90]);
    DailyTransaction::create(['date' => '2026-09-16', 'type' => TransactionType::Savings->value, 'amount' => 49.67]);
    DailyTransaction::create(['date' => '2026-10-03', 'type' => TransactionType::Income->value, 'amount' => 1000]);

    $horizon = app(BalanceCalculator::class)->horizonGrid($user, 2026, 9, 2);

    expect($horizon[0]['totals'])->toBe([
        'income' => '0.00',
        'expense' => '159.90',
        'forecast' => '0.00',
        'savings' => '49.67',
        'card' => '19.90',
    ])->and($horizon[1]['totals'])->toBe([
        'income' => '1000.00',
        'expense' => '0.00',
        'forecast' => '0.00',
        'savings' => '0.00',
        'card' => '0.00',
    ]);
});

it('applies the forecast daily rate from today across the month boundary', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1132.42, 'base_date' => '2026-09-01']);

    DailyTransaction::create(['date' => '2026-09-05', 'type' => TransactionType::Expense->value, 'amount' => 159.90]);
    DailyTransaction::create(['date' => '2026-09-12', 'type' => TransactionType::Card->value, 'amount' => 19.90]);

    DailyTransaction::create([
        'date' => '2026-09-23',
        'type' => TransactionType::Expense->value,
        'amount' => 118,
        'status' => TransactionStatus::Pending->value,
        'is_recurring' => true,
    ]);

    DailyForecast::create(['description' => 'Diário', 'amount' => 1490.10]);

    $horizon = app(BalanceCalculator::class)->horizonGrid($user, 2026, 9, 2);
    $september = collect($horizon[0]['days'])->keyBy('day');
    $october = collect($horizon[1]['days'])->keyBy('day');

    expect($september[5]['balance'])->toBe('972.52')
        ->and($september[12]['balance'])->toBe('952.62')
        ->and($september[14]['forecast'])->toBe('0.00')
        ->and($september[14]['balance'])->toBe('952.62')
        ->and($september[15]['forecast'])->toBe('49.67')
        ->and($september[15]['balance'])->toBe('902.95')
        ->and($september[16]['balance'])->toBe('853.28')
        ->and($september[17]['balance'])->toBe('803.61')
        ->and($september[30]['balance'])->toBe('39.90')
        ->and($october[1]['balance'])->toBe('-9.77')
        ->and($horizon[0]['totals']['forecast'])->toBe('794.72');
});

it('flags today and future days and respects the base date in the horizon', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 100, 'base_date' => '2026-09-10']);

    DailyTransaction::create(['date' => '2026-09-05', 'type' => TransactionType::Income->value, 'amount' => 9999]);
    DailyTransaction::create(['date' => '2026-09-10', 'type' => TransactionType::Income->value, 'amount' => 50]);

    $days = collect(app(BalanceCalculator::class)->horizonGrid($user, 2026, 9, 1)[0]['days'])->keyBy('day');

    expect($days[5]['income'])->toBe('0.00')
        ->and($days[5]['balance'])->toBe('100.00')
        ->and($days[10]['income'])->toBe('50.00')
        ->and($days[10]['balance'])->toBe('150.00')
        ->and($days[14]['is_today'])->toBeFalse()
        ->and($days[14]['is_future'])->toBeFalse()
        ->and($days[15]['is_today'])->toBeTrue()
        ->and($days[15]['is_future'])->toBeFalse()
        ->and($days[16]['is_today'])->toBeFalse()
        ->and($days[16]['is_future'])->toBeTrue();
});

it('ignores day check-ins in the realized balance', function () {
    $this->travelTo('2026-01-31');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 0, 'base_date' => '2026-01-01']);

    DailyTransaction::create(['date' => '2026-01-02', 'type' => TransactionType::Income->value, 'amount' => 1000]);
    DayCheckIn::create(['date' => '2026-01-02']);

    expect(app(BalanceCalculator::class)->realized($user, '2026-01-02'))->toBe('1000.00');
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

it('subtracts the forecast daily amount from today onward', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);
    DailyForecast::create(['description' => 'Mercado', 'amount' => 300]);

    $days = collect(app(BalanceCalculator::class)->horizonGrid($user, 2026, 9, 1)[0]['days'])->keyBy('day');

    expect($days[14]['forecast'])->toBe('0.00')
        ->and($days[14]['balance'])->toBe('1000.00')
        ->and($days[15]['forecast'])->toBe('10.00')
        ->and($days[15]['balance'])->toBe('990.00')
        ->and($days[16]['balance'])->toBe('980.00')
        ->and($days[30]['balance'])->toBe('840.00');
});

it('totals the forecast for the days it applies in each month', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);
    DailyForecast::create(['description' => 'Mercado', 'amount' => 300]);

    $horizon = app(BalanceCalculator::class)->horizonGrid($user, 2026, 9, 2);

    expect($horizon[0]['totals']['forecast'])->toBe('160.00')
        ->and($horizon[1]['totals']['forecast'])->toBe('310.00');
});

it('carries the forecast days before a future horizon start into the opening balance', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);
    DailyForecast::create(['description' => 'Mercado', 'amount' => 300]);

    $november = app(BalanceCalculator::class)->horizonGrid($user, 2026, 11, 1)[0];

    expect($november['days'][0]['forecast'])->toBe('10.00')
        ->and($november['days'][0]['balance'])->toBe('520.00');
});

it('keeps overlapping horizons consistent with the forecast', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);
    DailyForecast::create(['description' => 'Mercado', 'amount' => 300]);

    $calculator = app(BalanceCalculator::class);
    $full = $calculator->horizonGrid($user, 2026, 9, 12);
    $late = $calculator->horizonGrid($user, 2026, 11, 3);

    expect($late[0]['days'][0]['balance'])->toBe($full[2]['days'][0]['balance'])
        ->and($late[0]['days'][0]['balance'])->toBe('520.00');
});

it('shows a zero forecast column when the user has no items', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);

    $days = collect(app(BalanceCalculator::class)->horizonGrid($user, 2026, 9, 1)[0]['days'])->keyBy('day');

    expect($days[20]['forecast'])->toBe('0.00')
        ->and($days[20]['balance'])->toBe('1000.00');
});

it('includes the forecast in the projected balance from today onward', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);
    DailyForecast::create(['description' => 'Mercado', 'amount' => 300]);

    $calculator = app(BalanceCalculator::class);

    expect($calculator->projected($user, '2026-09-14'))->toBe('1000.00')
        ->and($calculator->projected($user, '2026-09-15'))->toBe('990.00')
        ->and($calculator->projected($user, '2026-09-16'))->toBe('980.00');
});

it('never turns the forecast into a realized movement', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-01']);
    DailyForecast::create(['description' => 'Mercado', 'amount' => 300]);

    expect(app(BalanceCalculator::class)->realized($user, '2026-09-30'))->toBe('1000.00');
});

it('starts the forecast on the base date when it is in the future', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-20']);
    DailyForecast::create(['description' => 'Mercado', 'amount' => 300]);

    $days = collect(app(BalanceCalculator::class)->horizonGrid($user, 2026, 9, 1)[0]['days'])->keyBy('day');

    expect($days[19]['forecast'])->toBe('0.00')
        ->and($days[19]['balance'])->toBe('1000.00')
        ->and($days[20]['forecast'])->toBe('10.00')
        ->and($days[20]['balance'])->toBe('990.00')
        ->and($days[21]['balance'])->toBe('980.00');
});

it('includes the forecast in the projected balance from a future base date', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-20']);
    DailyForecast::create(['description' => 'Mercado', 'amount' => 300]);

    $calculator = app(BalanceCalculator::class);

    expect($calculator->projected($user, '2026-09-19'))->toBe('1000.00')
        ->and($calculator->projected($user, '2026-09-20'))->toBe('990.00')
        ->and($calculator->projected($user, '2026-09-21'))->toBe('980.00');
});

it('carries the forecast from the future base date into a later horizon', function () {
    $this->travelTo('2026-09-15');
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->saveInitialBalance(['amount' => 1000, 'base_date' => '2026-09-20']);
    DailyForecast::create(['description' => 'Mercado', 'amount' => 300]);

    $november = app(BalanceCalculator::class)->horizonGrid($user, 2026, 11, 1)[0];

    expect($november['days'][0]['forecast'])->toBe('10.00')
        ->and($november['days'][0]['balance'])->toBe('570.00');
});
