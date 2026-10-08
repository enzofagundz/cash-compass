<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Mcp\Servers\CashCompassServer;
use App\Mcp\Support\MutationLedger;
use App\Mcp\Tools\ConfirmTransactionTool;
use App\Mcp\Tools\CreateTransactionTool;
use App\Mcp\Tools\DeleteTransactionTool;
use App\Mcp\Tools\SkipTransactionTool;
use App\Mcp\Tools\UpdateTransactionTool;
use App\Models\AccountPlan;
use App\Models\DailyTransaction;
use App\Models\McpMutationAudit;
use App\Models\McpOperation;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserInitialBalance;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Contracts\Transport;
use Symfony\Component\HttpFoundation\StreamedResponse;

it('creates a manual transaction with exact money and tags', function () {
    $this->travelTo('2026-01-15');
    $account = User::factory()->create();
    useMcpAccount($account);

    $tag = Tag::factory()->for($account)->create(['name' => 'Casa']);

    CashCompassServer::tool(CreateTransactionTool::class, [
        'operation_key' => 'op-1',
        'date' => '2026-01-10',
        'type' => TransactionType::Income->value,
        'amount' => 45.5,
        'description' => 'Freelance',
        'status' => TransactionStatus::Realized->value,
        'tags' => [$tag->getKey()],
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('transaction.type', TransactionType::Income->value)
            ->where('transaction.amount', '45.50')
            ->where('transaction.is_recurring', false)
            ->where('transaction.status', TransactionStatus::Realized->value)
            ->where('transaction.tags.0.name', 'Casa')
            ->etc());

    $transaction = DailyTransaction::query()->forUser($account)->sole();

    expect($transaction->user_id)->toBe($account->id)
        ->and($transaction->amount)->toBe('45.50')
        ->and($transaction->is_recurring)->toBeFalse()
        ->and($transaction->tags->pluck('id')->all())->toBe([$tag->getKey()]);
});

it('rejects invalid money on create', function (float $amount) {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateTransactionTool::class, [
        'operation_key' => 'op-1',
        'date' => '2026-01-10',
        'type' => TransactionType::Expense->value,
        'amount' => $amount,
    ])->assertHasErrors(['amount']);

    expect(DailyTransaction::query()->count())->toBe(0)
        ->and(McpOperation::query()->count())->toBe(0);
})->with([0, -10, 10.555]);

it('rejects a tag of another account and leaves no partial changes', function () {
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $foreign = Tag::factory()->for($other)->create();

    CashCompassServer::tool(CreateTransactionTool::class, [
        'operation_key' => 'op-1',
        'date' => '2026-01-10',
        'type' => TransactionType::Expense->value,
        'amount' => 10,
        'tags' => [$foreign->getKey()],
    ])->assertHasErrors(['tags']);

    expect(DailyTransaction::query()->count())->toBe(0)
        ->and(McpOperation::query()->count())->toBe(0);
});

it('rejects an archived tag on create', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $archived = Tag::factory()->archived()->for($account)->create();

    CashCompassServer::tool(CreateTransactionTool::class, [
        'operation_key' => 'op-1',
        'date' => '2026-01-10',
        'type' => TransactionType::Expense->value,
        'amount' => 10,
        'tags' => [$archived->getKey()],
    ])->assertHasErrors(['tags']);
});

it('rejects a plan of another account on create', function () {
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $foreign = AccountPlan::factory()->for($other)->create();

    CashCompassServer::tool(CreateTransactionTool::class, [
        'operation_key' => 'op-1',
        'date' => '2026-01-10',
        'type' => TransactionType::Expense->value,
        'amount' => 10,
        'account_plan_id' => $foreign->getKey(),
    ])->assertHasErrors(['Plano não encontrado']);
});

it('requires an operation key to create', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateTransactionTool::class, [
        'date' => '2026-01-10',
        'type' => TransactionType::Expense->value,
        'amount' => 10,
    ])->assertHasErrors(['operation key']);

    expect(DailyTransaction::query()->count())->toBe(0);
});

it('replays the previous result for the same key and arguments', function () {
    $this->travelTo('2026-01-15');
    $account = User::factory()->create();
    useMcpAccount($account);

    $arguments = [
        'operation_key' => 'op-1',
        'date' => '2026-01-10',
        'type' => TransactionType::Expense->value,
        'amount' => 30,
        'description' => 'Mercado',
    ];

    CashCompassServer::tool(CreateTransactionTool::class, $arguments)->assertOk();
    $firstId = (int) DailyTransaction::query()->forUser($account)->sole()->getKey();

    CashCompassServer::tool(CreateTransactionTool::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('transaction.id', $firstId)
            ->etc());

    expect(DailyTransaction::query()->count())->toBe(1)
        ->and(McpOperation::query()->count())->toBe(1)
        ->and(McpMutationAudit::query()->where('result', 'replayed')->count())->toBe(1);
});

it('rejects the same key with different arguments', function () {
    $this->travelTo('2026-01-15');
    $account = User::factory()->create();
    useMcpAccount($account);

    $base = [
        'operation_key' => 'op-1',
        'date' => '2026-01-10',
        'type' => TransactionType::Expense->value,
        'amount' => 30,
    ];

    CashCompassServer::tool(CreateTransactionTool::class, $base)->assertOk();

    CashCompassServer::tool(CreateTransactionTool::class, [...$base, 'amount' => 99])
        ->assertHasErrors(['chave de operação']);

    expect(DailyTransaction::query()->count())->toBe(1)
        ->and(McpMutationAudit::query()->where('result', 'conflict')->count())->toBe(1);
});

it('scopes the operation key to the account', function () {
    $this->travelTo('2026-01-15');
    $first = User::factory()->create();
    $second = User::factory()->create();

    $arguments = [
        'operation_key' => 'shared',
        'date' => '2026-01-10',
        'type' => TransactionType::Expense->value,
        'amount' => 30,
    ];

    useMcpAccount($first);
    CashCompassServer::tool(CreateTransactionTool::class, $arguments)->assertOk();

    useMcpAccount($second);
    CashCompassServer::tool(CreateTransactionTool::class, $arguments)->assertOk();

    expect(DailyTransaction::query()->count())->toBe(2)
        ->and(McpOperation::query()->count())->toBe(2);
});

it('enforces the unique operation key at the database level', function () {
    $account = User::factory()->create();

    McpOperation::query()->create([
        'user_id' => $account->id,
        'operation_key' => 'op-1',
        'tool' => 'create_transaction',
        'arguments_hash' => str_repeat('a', 64),
        'result' => ['transaction' => []],
    ]);

    McpOperation::query()->create([
        'user_id' => $account->id,
        'operation_key' => 'op-1',
        'tool' => 'create_transaction',
        'arguments_hash' => str_repeat('b', 64),
        'result' => ['transaction' => []],
    ]);
})->throws(UniqueConstraintViolationException::class);

it('updates a transaction and preserves an archived linked tag', function () {
    $this->travelTo('2026-01-15');
    $account = User::factory()->create();
    useMcpAccount($account);

    $archived = Tag::factory()->archived()->for($account)->create(['name' => 'Antiga']);
    $active = Tag::factory()->for($account)->create(['name' => 'Nova']);

    $transaction = DailyTransaction::factory()->for($account)->create([
        'date' => '2026-01-10',
        'amount' => 10,
        'description' => 'Original',
    ]);
    $transaction->tags()->attach($archived);

    CashCompassServer::tool(UpdateTransactionTool::class, [
        'id' => $transaction->getKey(),
        'amount' => 25,
        'description' => 'Atualizado',
        'tags' => [$archived->getKey(), $active->getKey()],
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('transaction.amount', '25.00')
            ->where('transaction.description', 'Atualizado')
            ->etc());

    $transaction->refresh();

    expect($transaction->amount)->toBe('25.00')
        ->and($transaction->description)->toBe('Atualizado')
        ->and($transaction->tags->pluck('id')->sort()->values()->all())
        ->toBe(collect([$archived->getKey(), $active->getKey()])->sort()->values()->all());
});

it('rejects attaching a new archived tag on update', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $archived = Tag::factory()->archived()->for($account)->create();
    $transaction = DailyTransaction::factory()->for($account)->create();

    CashCompassServer::tool(UpdateTransactionTool::class, [
        'id' => $transaction->getKey(),
        'tags' => [$archived->getKey()],
    ])->assertHasErrors(['tags']);
});

it('does not update another account transaction', function () {
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $foreign = DailyTransaction::factory()->for($other)->create(['amount' => 10]);

    CashCompassServer::tool(UpdateTransactionTool::class, [
        'id' => $foreign->getKey(),
        'amount' => 99,
    ])->assertHasErrors(['não encontrado']);

    expect($foreign->refresh()->amount)->toBe('10.00');
});

it('confirms a pending transaction and records the audit', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $pending = DailyTransaction::factory()->pending()->for($account)->create();

    CashCompassServer::tool(ConfirmTransactionTool::class, ['id' => $pending->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('transaction.status', TransactionStatus::Realized->value)
            ->etc());

    expect($pending->refresh()->status)->toBe(TransactionStatus::Realized)
        ->and(McpMutationAudit::query()->where('tool', 'confirm_transaction')->where('result', 'confirmed')->count())->toBe(1);
});

it('does not confirm a transaction that is not pending', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $realized = DailyTransaction::factory()->for($account)->create(['status' => TransactionStatus::Realized]);

    CashCompassServer::tool(ConfirmTransactionTool::class, ['id' => $realized->getKey()])
        ->assertHasErrors(['pendente']);

    expect($realized->refresh()->status)->toBe(TransactionStatus::Realized);
});

it('skips a pending transaction without deleting it', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $pending = DailyTransaction::factory()->pending()->for($account)->create();

    CashCompassServer::tool(SkipTransactionTool::class, ['id' => $pending->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('transaction.status', TransactionStatus::Skipped->value)
            ->etc());

    expect($pending->refresh()->status)->toBe(TransactionStatus::Skipped)
        ->and($pending->exists)->toBeTrue();
});

it('does not skip a transaction that is not pending', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $realized = DailyTransaction::factory()->for($account)->create(['status' => TransactionStatus::Realized]);

    CashCompassServer::tool(SkipTransactionTool::class, ['id' => $realized->getKey()])
        ->assertHasErrors(['pendente']);

    expect($realized->refresh()->status)->toBe(TransactionStatus::Realized);
});

it('deletes a manual transaction', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $manual = DailyTransaction::factory()->for($account)->create();

    CashCompassServer::tool(DeleteTransactionTool::class, ['id' => $manual->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('deleted', true)
            ->where('id', (int) $manual->getKey())
            ->etc());

    expect(DailyTransaction::query()->find($manual->getKey()))->toBeNull()
        ->and(McpMutationAudit::query()->where('tool', 'delete_transaction')->where('result', 'deleted')->count())->toBe(1);
});

it('blocks deleting a recurring transaction', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $recurring = DailyTransaction::factory()->pending()->for($account)->create();

    CashCompassServer::tool(DeleteTransactionTool::class, ['id' => $recurring->getKey()])
        ->assertHasErrors(['recorrente']);

    expect($recurring->refresh()->exists)->toBeTrue();
});

it('does not delete another account transaction', function () {
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $foreign = DailyTransaction::factory()->for($other)->create();

    CashCompassServer::tool(DeleteTransactionTool::class, ['id' => $foreign->getKey()])
        ->assertHasErrors(['não encontrado']);

    expect($foreign->refresh()->exists)->toBeTrue();
});

it('marks the delete tool as destructive', function () {
    expect((new DeleteTransactionTool)->annotations())
        ->toHaveKey('destructiveHint', true);
});

it('rolls back the mutation and the operation key when the mutation fails', function () {
    $account = User::factory()->create();
    $ledger = app(MutationLedger::class);

    expect(fn () => $ledger->run(
        $account,
        'create_transaction',
        'op-1',
        ['date' => '2026-01-10'],
        function () use ($account): array {
            DailyTransaction::factory()->for($account)->create();

            throw new RuntimeException('falha simulada');
        },
    ))->toThrow(RuntimeException::class);

    expect(DailyTransaction::query()->count())->toBe(0)
        ->and(McpOperation::query()->count())->toBe(0);
});

it('keeps only minimal information in the mutation history', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateTransactionTool::class, [
        'operation_key' => 'op-1',
        'date' => '2026-01-10',
        'type' => TransactionType::Expense->value,
        'amount' => 123.45,
        'description' => 'Segredo',
    ])->assertOk();

    $columns = Schema::getColumnListing('mcp_mutation_audits');
    $audit = McpMutationAudit::query()->sole();

    expect($columns)->not->toContain('amount')
        ->and($columns)->not->toContain('description')
        ->and($columns)->not->toContain('arguments')
        ->and($audit->result)->toBe('created')
        ->and($audit->auditable_type)->toBe(DailyTransaction::class)
        ->and($audit->auditable_id)->not->toBeNull();
});

it('returns a fresh balance after a mutation in the same process', function () {
    $this->travelTo('2026-01-15');
    $account = User::factory()->create();
    useMcpAccount($account);

    UserInitialBalance::factory()->for($account)->create(['amount' => 100, 'base_date' => '2026-01-01']);

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

    $realized = fn (int $index): string => (string) data_get($transport->messages[$index], 'result.structuredContent.realized');

    $call(1, 'get_balance', []);
    expect($realized(0))->toBe('100.00');

    $call(2, 'create_transaction', [
        'operation_key' => 'op-1',
        'date' => '2026-01-05',
        'type' => TransactionType::Income->value,
        'amount' => 250,
        'status' => TransactionStatus::Realized->value,
    ]);

    $call(3, 'get_balance', []);
    expect($realized(2))->toBe('350.00');
});
