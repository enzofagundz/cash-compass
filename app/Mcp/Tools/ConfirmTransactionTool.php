<?php

namespace App\Mcp\Tools;

use App\Enums\TransactionStatus;
use App\Mcp\AccountTool;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Models\DailyTransaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class ConfirmTransactionTool extends AccountTool
{
    use SerializesDomainRecords;

    protected string $name = 'confirm_transaction';

    protected string $title = 'Confirmar lançamento';

    protected string $description = 'Confirma um lançamento pendente da conta fixa, registrando sua realização. Somente lançamentos pendentes podem ser confirmados.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador do lançamento pendente.')->required(),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $transaction = DailyTransaction::query()
            ->forUser($account)
            ->find((int) $request->get('id'));

        if (! $transaction instanceof DailyTransaction) {
            return Response::error('Lançamento não encontrado.');
        }

        if ($transaction->status !== TransactionStatus::Pending) {
            throw ValidationException::withMessages([
                'id' => 'Somente lançamentos pendentes podem ser confirmados.',
            ]);
        }

        $transaction->update(['status' => TransactionStatus::Realized]);
        $ledger->record($account, 'confirm_transaction', 'confirmed', $transaction);

        return Response::structured([
            'transaction' => $this->transactionRecord($transaction->refresh()->load('tags')),
        ]);
    }
}
