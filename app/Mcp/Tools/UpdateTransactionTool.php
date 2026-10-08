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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdateTransactionTool extends AccountTool
{
    use ResolvesTransactionRelations;
    use SerializesDomainRecords;

    protected string $name = 'update_transaction';

    protected string $title = 'Editar lançamento';

    protected string $description = 'Edita campos de um lançamento da conta fixa. Aceita data, tipo, valor exato, descrição, plano, status e tags. Não altera a origem (manual/recorrente) nem aceita identidade.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador do lançamento.')->required(),
            'date' => $schema->string()->description('Nova data (AAAA-MM-DD).'),
            'type' => $schema->string()->enum(TransactionType::values())->description('Novo tipo.'),
            'amount' => $schema->number()->description('Novo valor exato, maior que zero, com até duas casas decimais.'),
            'description' => $schema->string()->description('Nova descrição (até 255 caracteres).'),
            'account_plan_id' => $schema->integer()->description('Novo plano ativo da conta, ou nulo para remover.'),
            'status' => $schema->string()->enum($this->statusValues())->description('Novo status.'),
            'tags' => $schema->array()->items($schema->integer())->description('Nova lista de tags da conta.'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'id' => ['required', 'integer'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'type' => ['sometimes', Rule::enum(TransactionType::class)],
            'amount' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,2'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_plan_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', Rule::enum(TransactionStatus::class)],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer'],
        ]);

        $transaction = DailyTransaction::query()
            ->forUser($account)
            ->find((int) $request->get('id'));

        if (! $transaction instanceof DailyTransaction) {
            return Response::error('Lançamento não encontrado.');
        }

        $input = $request->all();
        $changes = [];

        foreach (['date', 'type', 'status', 'description'] as $field) {
            if (array_key_exists($field, $input)) {
                $changes[$field] = $input[$field];
            }
        }

        if (array_key_exists('amount', $input)) {
            $changes['amount'] = number_format((float) $input['amount'], 2, '.', '');
        }

        if (array_key_exists('account_plan_id', $input)) {
            $changes['account_plan_id'] = $this->resolveAccountPlanId($account, $request);
        }

        $syncTags = array_key_exists('tags', $input);
        $tagIds = $syncTags
            ? $this->resolveTagIds($account, $request, $transaction->tags()->pluck('tags.id')->all())
            : [];

        if ($changes === [] && ! $syncTags) {
            throw ValidationException::withMessages([
                'id' => 'Informe ao menos um campo para atualizar.',
            ]);
        }

        try {
            DB::transaction(function () use ($transaction, $changes, $syncTags, $tagIds, $account, $ledger): void {
                if ($changes !== []) {
                    $transaction->update($changes);
                }

                if ($syncTags) {
                    $transaction->tags()->sync($tagIds);
                }

                $ledger->record($account, 'update_transaction', 'updated', $transaction);
            });
        } catch (UniqueConstraintViolationException) {
            return Response::error('Já existe um lançamento para esse plano na data informada.');
        }

        return Response::structured([
            'transaction' => $this->transactionRecord($transaction->refresh()->load('tags')),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function statusValues(): array
    {
        return array_map(fn (TransactionStatus $status): string => $status->value, TransactionStatus::cases());
    }
}
