<?php

use App\Mcp\Servers\CashCompassServer;
use App\Mcp\Tools\CreateDailyForecastTool;
use App\Mcp\Tools\DeleteDailyForecastTool;
use App\Mcp\Tools\SetDayCheckInTool;
use App\Mcp\Tools\SetForecastDivisorTool;
use App\Mcp\Tools\UpdateDailyForecastTool;
use App\Mcp\Tools\UpdateInitialBalanceTool;
use App\Models\DailyForecast;
use App\Models\DayCheckIn;
use App\Models\McpMutationAudit;
use App\Models\McpOperation;
use App\Models\User;
use App\Models\UserInitialBalance;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Contracts\Transport;
use Symfony\Component\HttpFoundation\StreamedResponse;

it('creates the initial balance with exact money and base date', function () {
    $this->travelTo('2026-03-10');
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(UpdateInitialBalanceTool::class, [
        'operation_key' => 'op-1',
        'amount' => 2500.5,
        'base_date' => '2026-02-01',
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('initial_balance.amount', '2500.50')
            ->where('initial_balance.base_date', '2026-02-01')
            ->where('initial_balance.starts_at', '2026-02-01')
            ->where('realized', '2500.50')
            ->where('projected', '2500.50')
            ->etc());

    $balance = UserInitialBalance::query()->forUser($account)->sole();

    expect($balance->user_id)->toBe($account->id)
        ->and($balance->amount)->toBe('2500.50');
});

it('stores zero and negative initial balance as exact decimal strings', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(UpdateInitialBalanceTool::class, [
        'operation_key' => 'op-zero',
        'amount' => 0,
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('initial_balance.amount', '0.00')
            ->etc());

    CashCompassServer::tool(UpdateInitialBalanceTool::class, [
        'operation_key' => 'op-negative',
        'amount' => -5.5,
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('initial_balance.amount', '-5.50')
            ->etc());

    expect(UserInitialBalance::query()->forUser($account)->sole()->amount)->toBe('-5.50');
});

it('treats an empty base date as the creation date', function () {
    $this->travelTo('2026-03-10');
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(UpdateInitialBalanceTool::class, [
        'operation_key' => 'op-1',
        'amount' => 100,
        'base_date' => null,
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('initial_balance.base_date', null)
            ->where('initial_balance.starts_at', '2026-03-10')
            ->etc());
});

it('keeps the existing base date when it is omitted on update', function () {
    $this->travelTo('2026-03-10');
    $account = User::factory()->create();
    useMcpAccount($account);

    UserInitialBalance::factory()->for($account)->create([
        'amount' => 100,
        'base_date' => '2026-02-01',
    ]);

    CashCompassServer::tool(UpdateInitialBalanceTool::class, [
        'operation_key' => 'op-2',
        'amount' => 300,
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('initial_balance.amount', '300.00')
            ->where('initial_balance.base_date', '2026-02-01')
            ->where('initial_balance.starts_at', '2026-02-01')
            ->etc());
});

it('replays the initial balance for the same key and arguments', function () {
    $this->travelTo('2026-03-10');
    $account = User::factory()->create();
    useMcpAccount($account);

    $arguments = ['operation_key' => 'op-1', 'amount' => 100, 'base_date' => '2026-02-01'];

    CashCompassServer::tool(UpdateInitialBalanceTool::class, $arguments)->assertOk();
    CashCompassServer::tool(UpdateInitialBalanceTool::class, $arguments)->assertOk();

    expect(UserInitialBalance::query()->count())->toBe(1)
        ->and(McpOperation::query()->count())->toBe(1)
        ->and(McpMutationAudit::query()->where('result', 'replayed')->count())->toBe(1);
});

it('rejects the same initial balance key with different arguments', function () {
    $this->travelTo('2026-03-10');
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(UpdateInitialBalanceTool::class, [
        'operation_key' => 'op-1',
        'amount' => 100,
    ])->assertOk();

    CashCompassServer::tool(UpdateInitialBalanceTool::class, [
        'operation_key' => 'op-1',
        'amount' => 999,
    ])->assertHasErrors(['chave de operação']);

    expect(UserInitialBalance::query()->sole()->amount)->toBe('100.00')
        ->and(McpMutationAudit::query()->where('result', 'conflict')->count())->toBe(1);
});

it('rejects an initial balance with more than two decimals', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(UpdateInitialBalanceTool::class, [
        'operation_key' => 'op-1',
        'amount' => 10.555,
    ])->assertHasErrors(['amount']);

    expect(UserInitialBalance::query()->count())->toBe(0)
        ->and(McpOperation::query()->count())->toBe(0);
});

it('does not change another account initial balance', function () {
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $foreign = UserInitialBalance::factory()->for($other)->create(['amount' => 50]);

    CashCompassServer::tool(UpdateInitialBalanceTool::class, [
        'operation_key' => 'op-1',
        'amount' => 700,
    ])->assertOk();

    expect($foreign->refresh()->amount)->toBe('50.00');
});

it('rejects identity arguments on the initial balance tool', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(UpdateInitialBalanceTool::class, [
        'operation_key' => 'op-1',
        'amount' => 100,
        'user_id' => 999,
    ])->assertHasErrors(['identidade']);
});

it('marks the initial balance tool as destructive', function () {
    expect((new UpdateInitialBalanceTool)->annotations())
        ->toHaveKey('destructiveHint', true);
});

it('creates a forecast item with exact money and trims the description', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateDailyForecastTool::class, [
        'operation_key' => 'op-1',
        'description' => '  Mercado do mês  ',
        'amount' => 1200.5,
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('forecast.description', 'Mercado do mês')
            ->where('forecast.amount', '1200.50')
            ->etc());

    $forecast = DailyForecast::query()->forUser($account)->sole();

    expect($forecast->user_id)->toBe($account->id)
        ->and($forecast->amount)->toBe('1200.50');
});

it('stores the forecast amount as an exact decimal string at maximum precision', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateDailyForecastTool::class, [
        'operation_key' => 'op-max',
        'description' => 'Máximo',
        'amount' => 99999999.99,
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('forecast.amount', '99999999.99')
            ->etc());

    expect(DailyForecast::query()->sole()->amount)->toBe('99999999.99');
});

it('replays a forecast creation for the same key and arguments', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $arguments = ['operation_key' => 'op-1', 'description' => 'Mercado', 'amount' => 300];

    CashCompassServer::tool(CreateDailyForecastTool::class, $arguments)->assertOk();
    $firstId = (int) DailyForecast::query()->sole()->getKey();

    CashCompassServer::tool(CreateDailyForecastTool::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('forecast.id', $firstId)
            ->etc());

    expect(DailyForecast::query()->count())->toBe(1)
        ->and(McpOperation::query()->count())->toBe(1);
});

it('rejects a forecast creation with invalid money', function (float $amount) {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateDailyForecastTool::class, [
        'operation_key' => 'op-1',
        'description' => 'Mercado',
        'amount' => $amount,
    ])->assertHasErrors(['amount']);

    expect(DailyForecast::query()->count())->toBe(0)
        ->and(McpOperation::query()->count())->toBe(0);
})->with([0, -10, 10.555]);

it('edits a forecast item', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $forecast = DailyForecast::factory()->for($account)->create([
        'description' => 'Mercado',
        'amount' => 1200,
    ]);

    CashCompassServer::tool(UpdateDailyForecastTool::class, [
        'id' => $forecast->getKey(),
        'description' => 'Mercado e feira',
        'amount' => 1500,
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('forecast.description', 'Mercado e feira')
            ->where('forecast.amount', '1500.00')
            ->etc());

    expect($forecast->refresh()->amount)->toBe('1500.00');
});

it('does not edit another account forecast item', function () {
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $foreign = DailyForecast::factory()->for($other)->create(['amount' => 500]);

    CashCompassServer::tool(UpdateDailyForecastTool::class, [
        'id' => $foreign->getKey(),
        'amount' => 999,
    ])->assertHasErrors(['não encontrado']);

    expect($foreign->refresh()->amount)->toBe('500.00');
});

it('deletes a forecast item and records the audit', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $forecast = DailyForecast::factory()->for($account)->create();

    CashCompassServer::tool(DeleteDailyForecastTool::class, ['id' => $forecast->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('deleted', true)
            ->where('id', (int) $forecast->getKey())
            ->etc());

    expect(DailyForecast::query()->find($forecast->getKey()))->toBeNull()
        ->and(McpMutationAudit::query()->where('tool', 'delete_daily_forecast')->where('result', 'deleted')->count())->toBe(1);
});

it('does not delete another account forecast item', function () {
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $foreign = DailyForecast::factory()->for($other)->create();

    CashCompassServer::tool(DeleteDailyForecastTool::class, ['id' => $foreign->getKey()])
        ->assertHasErrors(['não encontrado']);

    expect($foreign->refresh()->exists)->toBeTrue();
});

it('marks the forecast delete tool as destructive', function () {
    expect((new DeleteDailyForecastTool)->annotations())
        ->toHaveKey('destructiveHint', true);
});

it('updates the forecast divisor and recalculates the daily amount', function () {
    $account = User::factory()->create(['forecast_divisor_days' => 30]);
    useMcpAccount($account);

    DailyForecast::factory()->for($account)->create(['amount' => 300]);

    CashCompassServer::tool(SetForecastDivisorTool::class, ['divisor_days' => 15])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('divisor_days', 15)
            ->where('monthly_total', '300.00')
            ->where('daily_amount', '20.00')
            ->etc());

    expect($account->refresh()->forecast_divisor_days)->toBe(15);
});

it('accepts only a divisor between one and thirty one', function (int $divisor) {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(SetForecastDivisorTool::class, ['divisor_days' => $divisor])
        ->assertHasErrors(['divisor days']);

    expect($account->refresh()->forecast_divisor_days)->toBe(30);
})->with([0, 32]);

it('accepts the divisor boundaries', function (int $divisor) {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(SetForecastDivisorTool::class, ['divisor_days' => $divisor])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('divisor_days', $divisor)
            ->etc());
})->with([1, 31]);

it('does not change another account divisor', function () {
    $account = User::factory()->create();
    $other = User::factory()->create(['forecast_divisor_days' => 30]);
    useMcpAccount($account);

    CashCompassServer::tool(SetForecastDivisorTool::class, ['divisor_days' => 5])->assertOk();

    expect($other->refresh()->forecast_divisor_days)->toBe(30);
});

it('sets an explicit check-in state', function () {
    $this->travelTo('2026-03-10');
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(SetDayCheckInTool::class, ['date' => '2026-03-09', 'checked_in' => true])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('date', '2026-03-09')
            ->where('checked_in', true)
            ->where('changed', true)
            ->etc());

    expect(DayCheckIn::query()->forUser($account)->where('date', '2026-03-09')->exists())->toBeTrue()
        ->and(McpMutationAudit::query()->where('tool', 'set_day_check_in')->where('result', 'checked_in')->count())->toBe(1);

    CashCompassServer::tool(SetDayCheckInTool::class, ['date' => '2026-03-09', 'checked_in' => false])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('checked_in', false)
            ->where('changed', true)
            ->etc());

    expect(DayCheckIn::query()->forUser($account)->where('date', '2026-03-09')->exists())->toBeFalse()
        ->and(McpMutationAudit::query()->where('result', 'unchecked_in')->count())->toBe(1);
});

it('does not duplicate or audit a repeated check-in state', function () {
    $this->travelTo('2026-03-10');
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(SetDayCheckInTool::class, ['date' => '2026-03-09', 'checked_in' => true])->assertOk();

    CashCompassServer::tool(SetDayCheckInTool::class, ['date' => '2026-03-09', 'checked_in' => true])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('checked_in', true)
            ->where('changed', false)
            ->etc());

    expect(DayCheckIn::query()->forUser($account)->where('date', '2026-03-09')->count())->toBe(1)
        ->and(McpMutationAudit::query()->where('tool', 'set_day_check_in')->count())->toBe(1);
});

it('rejects a future check-in', function () {
    $this->travelTo('2026-03-10');
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(SetDayCheckInTool::class, ['date' => '2026-03-11', 'checked_in' => true])
        ->assertHasErrors(['dia futuro']);

    expect(DayCheckIn::query()->count())->toBe(0);
});

it('does not touch another account check-in', function () {
    $this->travelTo('2026-03-10');
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $foreign = DayCheckIn::factory()->for($other)->create(['date' => '2026-03-09']);

    CashCompassServer::tool(SetDayCheckInTool::class, ['date' => '2026-03-09', 'checked_in' => false])->assertOk();

    expect($foreign->refresh()->exists)->toBeTrue();
});

it('returns fresh balance and forecast after mutations in the same process', function () {
    $this->travelTo('2026-03-10');
    $account = User::factory()->create();
    useMcpAccount($account);

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

    $call = function (int $id, string $name, array $arguments) use ($server): void {
        $server->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ], JSON_THROW_ON_ERROR));
    };

    $value = fn (int $index, string $path): mixed => data_get($transport->messages[$index], 'result.structuredContent.'.$path);

    $call(1, 'get_balance', []);
    expect($value(0, 'realized'))->toBe('0.00');

    $call(2, 'update_initial_balance', ['operation_key' => 'op-1', 'amount' => 100, 'base_date' => '2026-03-01']);
    $call(3, 'get_balance', []);
    expect($value(2, 'realized'))->toBe('100.00');

    $call(4, 'list_daily_forecasts', []);
    expect($value(3, 'monthly_total'))->toBe('0.00');

    $call(5, 'create_daily_forecast', ['operation_key' => 'op-2', 'description' => 'Mercado', 'amount' => 300]);
    $call(6, 'list_daily_forecasts', []);
    expect($value(5, 'monthly_total'))->toBe('300.00');
});
