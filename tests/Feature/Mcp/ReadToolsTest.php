<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Mcp\Servers\CashCompassServer;
use App\Mcp\Tools\GetBalanceTool;
use App\Mcp\Tools\GetHorizonTool;
use App\Mcp\Tools\GetInitialBalanceTool;
use App\Mcp\Tools\GetTransactionTool;
use App\Mcp\Tools\ListDailyForecastsTool;
use App\Mcp\Tools\ListTagsTool;
use App\Mcp\Tools\ListTransactionsTool;
use App\Models\DailyForecast;
use App\Models\DailyTransaction;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserInitialBalance;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Contracts\Transport;
use Symfony\Component\HttpFoundation\StreamedResponse;

it('lists only the fixed account transactions and applies filters', function () {
    $this->travelTo('2026-01-15');
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $kept = DailyTransaction::factory()->income()->for($account)->create([
        'date' => '2026-01-10',
        'amount' => 500,
        'status' => TransactionStatus::Realized,
    ]);
    DailyTransaction::factory()->for($account)->create([
        'date' => '2026-01-12',
        'amount' => 100,
        'status' => TransactionStatus::Pending,
    ]);
    DailyTransaction::factory()->for($other)->create([
        'date' => '2026-01-11',
        'amount' => 900,
    ]);

    CashCompassServer::tool(ListTransactionsTool::class, [
        'date_from' => '2026-01-10',
        'date_to' => '2026-01-11',
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('total', 1)
            ->has('items', 1)
            ->where('items.0.id', (int) $kept->getKey())
            ->where('items.0.type', TransactionType::Income->value)
            ->where('items.0.amount', '500.00')
            ->where('items.0.status', TransactionStatus::Realized->value)
            ->etc());
});

it('rejects an invalid date filter', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(ListTransactionsTool::class, ['date_from' => '10/01/2026'])
        ->assertHasErrors();
});

it('does not expose another account transaction', function () {
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $foreign = DailyTransaction::factory()->for($other)->create();

    CashCompassServer::tool(GetTransactionTool::class, ['id' => $foreign->getKey()])
        ->assertHasErrors(['não encontrado']);
});

it('returns a transaction of the fixed account by id', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $transaction = DailyTransaction::factory()->for($account)->create([
        'date' => '2026-01-03',
        'amount' => 42.5,
    ]);

    CashCompassServer::tool(GetTransactionTool::class, ['id' => $transaction->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('transaction.id', (int) $transaction->getKey())
            ->where('transaction.amount', '42.50')
            ->where('transaction.date', '2026-01-03')
            ->etc());
});

it('lists tags with usage counts and hides other accounts', function () {
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $used = Tag::factory()->for($account)->create(['name' => 'Casa']);
    Tag::factory()->archived()->for($account)->create(['name' => 'Antiga']);
    Tag::factory()->for($other)->create(['name' => 'Alheia']);

    DailyTransaction::factory()->for($account)->hasAttached($used)->create();

    CashCompassServer::tool(ListTagsTool::class, ['is_active' => true])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('total', 1)
            ->has('items', 1)
            ->where('items.0.name', 'Casa')
            ->where('items.0.daily_transactions_count', 1)
            ->where('items.0.account_plans_count', 0)
            ->etc());
});

it('returns the initial balance and its start date', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    UserInitialBalance::factory()->for($account)->create([
        'amount' => 2500.5,
        'base_date' => '2026-02-01',
    ]);

    CashCompassServer::tool(GetInitialBalanceTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('has_balance', true)
            ->where('balance.amount', '2500.50')
            ->where('balance.base_date', '2026-02-01')
            ->where('balance.starts_at', '2026-02-01')
            ->etc());
});

it('reports when the fixed account has no initial balance', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(GetInitialBalanceTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('has_balance', false)
            ->where('balance', null)
            ->etc());
});

it('summarizes the daily forecast with the canonical divisor', function () {
    $account = User::factory()->create(['forecast_divisor_days' => 30]);
    useMcpAccount($account);

    DailyForecast::factory()->for($account)->create(['amount' => 150]);
    DailyForecast::factory()->for($account)->create(['amount' => 150]);

    CashCompassServer::tool(ListDailyForecastsTool::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('monthly_total', '300.00')
            ->where('divisor_days', 30)
            ->where('daily_amount', '10.00')
            ->where('count', 2)
            ->has('items', 2)
            ->etc());
});

it('returns realized and projected balances from the canonical calculator', function () {
    $this->travelTo('2026-01-15');
    $account = User::factory()->create(['forecast_divisor_days' => 30]);
    useMcpAccount($account);

    UserInitialBalance::factory()->for($account)->create(['amount' => 1000, 'base_date' => '2026-01-01']);
    DailyTransaction::factory()->income()->for($account)->create(['date' => '2026-01-05', 'amount' => 500, 'status' => TransactionStatus::Realized]);
    DailyTransaction::factory()->for($account)->create(['date' => '2026-01-10', 'amount' => 200, 'status' => TransactionStatus::Realized]);
    DailyTransaction::factory()->pending()->for($account)->create(['date' => '2026-01-20', 'amount' => 300]);

    CashCompassServer::tool(GetBalanceTool::class, ['date' => '2026-01-15'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('date', '2026-01-15')
            ->where('realized', '1300.00')
            ->where('projected', '1300.00')
            ->etc());
});

it('returns the canonical horizon grid with projected balances', function () {
    $this->travelTo('2026-01-15');
    $account = User::factory()->create(['forecast_divisor_days' => 30]);
    useMcpAccount($account);

    UserInitialBalance::factory()->for($account)->create(['amount' => 1000, 'base_date' => '2026-01-01']);
    DailyForecast::factory()->for($account)->create(['amount' => 300]);
    DailyTransaction::factory()->income()->for($account)->create(['date' => '2026-01-05', 'amount' => 500, 'status' => TransactionStatus::Realized]);
    DailyTransaction::factory()->for($account)->create(['date' => '2026-01-10', 'amount' => 200, 'status' => TransactionStatus::Realized]);
    DailyTransaction::factory()->pending()->for($account)->create(['date' => '2026-01-20', 'amount' => 300]);

    CashCompassServer::tool(GetHorizonTool::class, ['year' => 2026, 'month' => 1, 'months' => 1])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('months', 1)
            ->has('horizon', 1)
            ->where('horizon.0.days.0.balance', '1000.00')
            ->where('horizon.0.days.4.balance', '1500.00')
            ->where('horizon.0.days.9.balance', '1300.00')
            ->where('horizon.0.days.14.balance', '1300.00')
            ->where('horizon.0.days.15.balance', '1290.00')
            ->where('horizon.0.days.19.balance', '950.00')
            ->where('horizon.0.totals.income', '500.00')
            ->where('horizon.0.totals.expense', '500.00')
            ->where('horizon.0.totals.daily', '160.00')
            ->etc());
});

it('does not keep stale identity or calculations across calls in one process', function () {
    $this->travelTo('2026-01-15');
    $first = User::factory()->create();
    $second = User::factory()->create();
    useMcpAccount($first);

    UserInitialBalance::factory()->for($first)->create(['amount' => 100, 'base_date' => '2026-01-01']);

    $transport = new class implements Transport
    {
        /** @var array<int, array<string, mixed>> */
        public array $messages = [];

        public function onReceive(Closure $handler): void {}

        public function send(string $message): void
        {
            $decoded = json_decode($message, true);

            if (is_array($decoded)) {
                $this->messages[] = $decoded;
            }
        }

        public function run(): HttpResponse|StreamedResponse
        {
            throw new LogicException('Not implemented.');
        }

        public function stream(Closure $stream): void
        {
            $stream();
        }
    };

    $server = app(CashCompassServer::class, ['transport' => $transport]);
    $server->start();

    $realized = fn (int $index): string => (string) data_get($transport->messages[$index], 'result.structuredContent.realized');

    $server->handle(json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'get_balance', 'arguments' => []],
    ], JSON_THROW_ON_ERROR));

    expect($realized(0))->toBe('100.00');

    DailyTransaction::factory()->income()->for($first)->create([
        'date' => '2026-01-05',
        'amount' => 250,
        'status' => TransactionStatus::Realized,
    ]);

    $server->handle(json_encode([
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/call',
        'params' => ['name' => 'get_balance', 'arguments' => []],
    ], JSON_THROW_ON_ERROR));

    expect($realized(1))->toBe('350.00');

    useMcpAccount($second);
    UserInitialBalance::factory()->for($second)->create(['amount' => 7, 'base_date' => '2026-01-01']);

    $server->handle(json_encode([
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/call',
        'params' => ['name' => 'get_balance', 'arguments' => []],
    ], JSON_THROW_ON_ERROR));

    expect($realized(2))->toBe('7.00');
});
