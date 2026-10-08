<?php

namespace App\Mcp\Tools;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Mcp\AccountTool;
use App\Mcp\Concerns\ResolvesTransactionRelations;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Models\DailyTransaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class CreateTransactionTool extends AccountTool
{
    use ResolvesTransactionRelations;
    use SerializesDomainRecords;

    protected string $name = 'create_transaction';

    protected string $title = 'Criar lançamento manual';

    protected string $description = 'Cria um lançamento manual na conta fixa. Exige uma chave de operação única por conta: repetir a mesma chave com os mesmos argumentos devolve o lançamento anterior sem duplicar; a mesma chave com argumentos diferentes é rejeitada. Não aceita identidade nem lançamentos recorrentes.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'operation_key' => $schema->string()->description('Chave de operação única por conta, para idempotência.')->required(),
            'date' => $schema->string()->description('Data do lançamento (AAAA-MM-DD).')->required(),
            'type' => $schema->string()->enum(TransactionType::values())->description('Tipo do lançamento.')->required(),
            'amount' => $schema->number()->description('Valor exato, maior que zero, com até duas casas decimais.')->required(),
            'description' => $schema->string()->description('Descrição opcional (até 255 caracteres).'),
            'account_plan_id' => $schema->integer()->description('Plano de contas ativo da conta, opcional.'),
            'status' => $schema->string()->enum($this->statusValues())->description('Status inicial (padrão: realizado).'),
            'tags' => $schema->array()->items($schema->integer())->description('Identificadores de tags ativas da conta.'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $validated = $request->validate([
            'operation_key' => ['required', 'string', 'max:191'],
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', Rule::enum(TransactionType::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_plan_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', Rule::enum(TransactionStatus::class)],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer'],
        ]);

        $tagIds = $this->resolveTagIds($account, $request);
        $planId = $this->resolveAccountPlanId($account, $request);

        $attributes = [
            'date' => $validated['date'],
            'type' => $validated['type'],
            'amount' => number_format((float) $validated['amount'], 2, '.', ''),
            'description' => $validated['description'] ?? null,
            'account_plan_id' => $planId,
            'status' => $validated['status'] ?? TransactionStatus::Realized->value,
        ];

        $result = $ledger->run(
            $account,
            'create_transaction',
            $validated['operation_key'],
            [...$attributes, 'tags' => collect($tagIds)->sort()->values()->all()],
            function () use ($account, $ledger, $attributes, $tagIds): array {
                $transaction = new DailyTransaction([
                    ...$attributes,
                    'is_recurring' => false,
                ]);
                $transaction->user_id = $account->getKey();
                $transaction->save();

                $transaction->tags()->sync($tagIds);
                $ledger->record($account, 'create_transaction', 'created', $transaction);

                return ['transaction' => $this->transactionRecord($transaction->load('tags'))];
            },
        );

        return Response::structured($result);
    }

    /**
     * @return array<int, string>
     */
    private function statusValues(): array
    {
        return array_map(fn (TransactionStatus $status): string => $status->value, TransactionStatus::cases());
    }
}
