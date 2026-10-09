<?php

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Mcp\Servers\CashCompassServer;
use App\Mcp\Tools\ActivateAccountPlanTool;
use App\Mcp\Tools\CreateAccountPlanTool;
use App\Mcp\Tools\DeactivateAccountPlanTool;
use App\Mcp\Tools\DeleteAccountPlanTool;
use App\Mcp\Tools\UpdateAccountPlanTool;
use App\Models\AccountPlan;
use App\Models\DailyTransaction;
use App\Models\McpMutationAudit;
use App\Models\McpOperation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Contracts\Transport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @return array<string, mixed>
 */
function accountPlanArguments(array $overrides = []): array
{
    return [
        'operation_key' => 'plan-1',
        'type' => TransactionType::Expense->value,
        'description' => 'Academia',
        'expected_amount' => 120,
        'frequency' => RecurrenceFrequency::Monthly->value,
        'day_of_month' => 5,
        'starts_at' => '2026-01-01',
        ...$overrides,
    ];
}

it('creates a monthly plan and generates the pending occurrences', function () {
    $this->travelTo('2026-01-01');
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments())
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('account_plan.type', TransactionType::Expense->value)
            ->where('account_plan.expected_amount', '120.00')
            ->where('account_plan.frequency', RecurrenceFrequency::Monthly->value)
            ->where('account_plan.is_active', true)
            ->etc());

    $plan = AccountPlan::query()->forUser($account)->sole();
    $transactions = $plan->dailyTransactions()->orderBy('date')->get();

    expect($plan->user_id)->toBe($account->id)
        ->and($transactions)->toHaveCount(12)
        ->and($transactions->first()->date->format('Y-m-d'))->toBe('2026-01-05')
        ->and($transactions->first()->status)->toBe(TransactionStatus::Pending)
        ->and($transactions->first()->is_recurring)->toBeTrue()
        ->and($transactions->first()->amount)->toBe('120.00')
        ->and(McpMutationAudit::query()->where('tool', 'create_account_plan')->where('result', 'created')->count())->toBe(1);
});

it('rejects the daily type on a plan', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments([
        'type' => TransactionType::Daily->value,
    ]))->assertHasErrors(['type']);

    expect(AccountPlan::query()->count())->toBe(0)
        ->and(McpOperation::query()->count())->toBe(0);
});

it('rejects invalid money on a plan', function (float $amount) {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments([
        'expected_amount' => $amount,
    ]))->assertHasErrors(['expected amount']);

    expect(AccountPlan::query()->count())->toBe(0)
        ->and(McpOperation::query()->count())->toBe(0);
})->with([0, -10, 10.555]);

it('stores the expected amount as an exact decimal string at maximum precision', function () {
    $this->travelTo('2026-01-01');
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments([
        'expected_amount' => 99999999.99,
    ]))
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('account_plan.expected_amount', '99999999.99')
            ->etc());

    expect(AccountPlan::query()->forUser($account)->sole()->expected_amount)->toBe('99999999.99');
});

it('requires an operation key to create a plan', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $arguments = accountPlanArguments();
    unset($arguments['operation_key']);

    CashCompassServer::tool(CreateAccountPlanTool::class, $arguments)
        ->assertHasErrors(['operation key']);

    expect(AccountPlan::query()->count())->toBe(0);
});

it('replays the previous result for the same plan key and arguments', function () {
    $this->travelTo('2026-01-01');
    $account = User::factory()->create();
    useMcpAccount($account);

    $arguments = accountPlanArguments();

    CashCompassServer::tool(CreateAccountPlanTool::class, $arguments)->assertOk();
    $firstId = (int) AccountPlan::query()->forUser($account)->sole()->getKey();

    CashCompassServer::tool(CreateAccountPlanTool::class, $arguments)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('account_plan.id', $firstId)
            ->etc());

    expect(AccountPlan::query()->count())->toBe(1)
        ->and(McpOperation::query()->count())->toBe(1)
        ->and(McpMutationAudit::query()->where('result', 'replayed')->count())->toBe(1);
});

it('rejects the same plan key with different arguments', function () {
    $this->travelTo('2026-01-01');
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments())->assertOk();

    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments([
        'expected_amount' => 999,
    ]))->assertHasErrors(['chave de operação']);

    expect(AccountPlan::query()->count())->toBe(1)
        ->and(McpMutationAudit::query()->where('result', 'conflict')->count())->toBe(1);
});

it('scopes the plan operation key to the account', function () {
    $this->travelTo('2026-01-01');
    $first = User::factory()->create();
    $second = User::factory()->create();

    useMcpAccount($first);
    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments())->assertOk();

    useMcpAccount($second);
    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments())->assertOk();

    expect(AccountPlan::query()->count())->toBe(2)
        ->and(McpOperation::query()->count())->toBe(2);
});

it('creates a plan with tags and propagates them to the occurrences', function () {
    $this->travelTo('2026-01-01');
    $account = User::factory()->create();
    useMcpAccount($account);

    $tag = Tag::factory()->for($account)->create(['name' => 'Assinaturas']);

    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments([
        'tags' => [$tag->getKey()],
    ]))->assertOk();

    $plan = AccountPlan::query()->forUser($account)->sole();

    expect($plan->tags->pluck('id')->all())->toBe([$tag->getKey()])
        ->and($plan->dailyTransactions()->orderBy('date')->first()->tags->pluck('id')->all())
        ->toBe([$tag->getKey()]);
});

it('rejects an archived tag on create', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $archived = Tag::factory()->archived()->for($account)->create();

    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments([
        'tags' => [$archived->getKey()],
    ]))->assertHasErrors(['tags']);

    expect(AccountPlan::query()->count())->toBe(0);
});

it('rejects another account tag on create', function () {
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $foreign = Tag::factory()->for($other)->create();

    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments([
        'tags' => [$foreign->getKey()],
    ]))->assertHasErrors(['tags']);

    expect(AccountPlan::query()->count())->toBe(0);
});

it('regenerates pending occurrences and preserves realized ones when editing', function () {
    $this->travelTo('2026-01-01');
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments())->assertOk();

    $plan = AccountPlan::query()->forUser($account)->sole();
    $realized = $plan->dailyTransactions()->orderBy('date')->first();
    $realized->update(['status' => TransactionStatus::Realized]);

    $this->travelTo('2026-02-01');

    CashCompassServer::tool(UpdateAccountPlanTool::class, [
        'id' => $plan->getKey(),
        'expected_amount' => 150,
    ])->assertOk();

    $pending = $plan->dailyTransactions()
        ->where('date', '>', '2026-02-01')
        ->orderBy('date')
        ->first();

    expect($realized->refresh()->amount)->toBe('120.00')
        ->and($realized->status)->toBe(TransactionStatus::Realized)
        ->and($pending->amount)->toBe('150.00')
        ->and($pending->status)->toBe(TransactionStatus::Pending);
});

it('preserves an archived linked tag when editing a plan', function () {
    $this->travelTo('2026-01-01');
    $account = User::factory()->create();
    useMcpAccount($account);

    $tag = Tag::factory()->archived()->for($account)->create(['name' => 'Antiga']);
    $plan = AccountPlan::factory()->for($account)->create(['description' => 'Original']);
    $plan->tags()->attach($tag);

    CashCompassServer::tool(UpdateAccountPlanTool::class, [
        'id' => $plan->getKey(),
        'description' => 'Atualizado',
        'tags' => [$tag->getKey()],
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('account_plan.description', 'Atualizado')
            ->etc());

    expect($plan->refresh()->tags->pluck('id')->all())->toBe([$tag->getKey()]);
});

it('rejects attaching a new archived tag on update', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $archived = Tag::factory()->archived()->for($account)->create();
    $plan = AccountPlan::factory()->for($account)->create();

    CashCompassServer::tool(UpdateAccountPlanTool::class, [
        'id' => $plan->getKey(),
        'tags' => [$archived->getKey()],
    ])->assertHasErrors(['tags']);
});

it('requires at least one field to update a plan', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    $plan = AccountPlan::factory()->for($account)->create();

    CashCompassServer::tool(UpdateAccountPlanTool::class, ['id' => $plan->getKey()])
        ->assertHasErrors(['ao menos um campo']);
});

it('does not update another account plan', function () {
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $foreign = AccountPlan::factory()->for($other)->create(['description' => 'Original']);

    CashCompassServer::tool(UpdateAccountPlanTool::class, [
        'id' => $foreign->getKey(),
        'description' => 'Invadido',
    ])->assertHasErrors(['não encontrado']);

    expect($foreign->refresh()->description)->toBe('Original');
});

it('activates a plan and regenerates its occurrences', function () {
    $this->travelTo('2026-01-01');
    $account = User::factory()->create();
    useMcpAccount($account);

    $plan = AccountPlan::factory()->inactive()->for($account)->create(['starts_at' => '2026-01-15']);

    expect($plan->dailyTransactions()->count())->toBe(0);

    CashCompassServer::tool(ActivateAccountPlanTool::class, ['id' => $plan->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('account_plan.is_active', true)
            ->etc());

    expect($plan->refresh()->is_active)->toBeTrue()
        ->and($plan->dailyTransactions()->count())->toBe(12)
        ->and(McpMutationAudit::query()->where('tool', 'activate_account_plan')->where('result', 'activated')->count())->toBe(1);
});

it('deactivates a plan and cancels future pending occurrences keeping realized ones', function () {
    $this->travelTo('2026-01-01');
    $account = User::factory()->create();
    useMcpAccount($account);

    $plan = AccountPlan::factory()->for($account)->create();
    $realized = $plan->dailyTransactions()->orderBy('date')->first();
    $realized->update(['status' => TransactionStatus::Realized]);

    $this->travelTo('2026-02-01');

    CashCompassServer::tool(DeactivateAccountPlanTool::class, ['id' => $plan->getKey()])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('account_plan.is_active', false)
            ->etc());

    expect($plan->refresh()->is_active)->toBeFalse()
        ->and($realized->refresh()->exists)->toBeTrue()
        ->and($realized->status)->toBe(TransactionStatus::Realized)
        ->and($plan->dailyTransactions()->where('status', TransactionStatus::Pending)->count())->toBe(0)
        ->and(McpMutationAudit::query()->where('tool', 'deactivate_account_plan')->where('result', 'deactivated')->count())->toBe(1);
});

it('blocks deleting a plan with a realized transaction up to today', function () {
    $this->travelTo('2026-01-01');
    $account = User::factory()->create();
    useMcpAccount($account);

    $plan = AccountPlan::factory()->for($account)->create();
    $plan->dailyTransactions()->orderBy('date')->first()->update(['status' => TransactionStatus::Realized]);

    $this->travelTo('2026-02-01');

    CashCompassServer::tool(DeleteAccountPlanTool::class, ['id' => $plan->getKey()])
        ->assertHasErrors(['realizados']);

    expect($plan->refresh()->exists)->toBeTrue();
});

it('deletes a plan without realized transactions and cancels its pending occurrences', function () {
    $this->travelTo('2026-01-01');
    $account = User::factory()->create();
    useMcpAccount($account);

    $plan = AccountPlan::factory()->for($account)->create(['starts_at' => '2026-01-15']);
    $planId = (int) $plan->getKey();

    expect($plan->dailyTransactions()->count())->toBe(12);

    CashCompassServer::tool(DeleteAccountPlanTool::class, ['id' => $planId])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('deleted', true)
            ->where('id', $planId)
            ->etc());

    expect(AccountPlan::query()->find($planId))->toBeNull()
        ->and(DailyTransaction::query()->forUser($account)->count())->toBe(0)
        ->and(McpMutationAudit::query()->where('tool', 'delete_account_plan')->where('result', 'deleted')->count())->toBe(1);
});

it('does not delete another account plan', function () {
    $account = User::factory()->create();
    $other = User::factory()->create();
    useMcpAccount($account);

    $foreign = AccountPlan::factory()->for($other)->create();

    CashCompassServer::tool(DeleteAccountPlanTool::class, ['id' => $foreign->getKey()])
        ->assertHasErrors(['não encontrado']);

    expect($foreign->refresh()->exists)->toBeTrue();
});

it('marks the delete account plan tool as destructive', function () {
    expect((new DeleteAccountPlanTool)->annotations())
        ->toHaveKey('destructiveHint', true);
});

it('rejects identity arguments on plan tools', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments([
        'user_id' => 999,
    ]))->assertHasErrors(['identidade']);
});

it('keeps only minimal information in the plan mutation history', function () {
    $this->travelTo('2026-01-01');
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments([
        'description' => 'Segredo',
    ]))->assertOk();

    $columns = Schema::getColumnListing('mcp_mutation_audits');
    $audit = McpMutationAudit::query()->sole();

    expect($columns)->not->toContain('description')
        ->and($columns)->not->toContain('expected_amount')
        ->and($audit->result)->toBe('created')
        ->and($audit->auditable_type)->toBe(AccountPlan::class)
        ->and($audit->auditable_id)->not->toBeNull();
});

it('falls back to the last day of the month for a nonexistent day', function () {
    $this->travelTo('2026-01-01');
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(CreateAccountPlanTool::class, accountPlanArguments([
        'day_of_month' => 31,
        'starts_at' => '2026-01-31',
    ]))->assertOk();

    $plan = AccountPlan::query()->forUser($account)->sole();

    expect($plan->dailyTransactions()->where('date', '2026-02-28')->exists())->toBeTrue();
});

it('returns a fresh plan read after a create in the same process', function () {
    $this->travelTo('2026-01-01');
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

    $call(1, 'create_account_plan', accountPlanArguments());

    $createdId = (int) data_get($transport->messages[0], 'result.structuredContent.account_plan.id');

    $call(2, 'get_account_plan', ['id' => $createdId]);

    expect($createdId)->toBeGreaterThan(0)
        ->and(data_get($transport->messages[1], 'result.structuredContent.account_plan.id'))->toBe($createdId)
        ->and(data_get($transport->messages[1], 'result.structuredContent.account_plan.expected_amount'))->toBe('120.00');
});
