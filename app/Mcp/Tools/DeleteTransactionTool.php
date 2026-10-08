<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Models\DailyTransaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteTransactionTool extends AccountTool
{
    protected string $name = 'delete_transaction';

    protected string $title = 'Excluir lançamento manual';

    protected string $description = 'Exclui um lançamento manual da conta fixa. Ação destrutiva e irreversível: o Hermes deve pedir confirmação na conversa, resumindo o lançamento e seu efeito, antes de chamar esta ferramenta. Lançamentos recorrentes não podem ser excluídos; use pular ocorrência ou desative o plano. A confirmação conversacional é uma proteção do agente, não uma comprovação de aprovação humana pelo servidor.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador do lançamento manual a excluir.')->required(),
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

        if ($transaction->is_recurring) {
            throw ValidationException::withMessages([
                'id' => 'Lançamentos recorrentes não podem ser excluídos; use pular ocorrência ou desative o plano.',
            ]);
        }

        $transactionId = (int) $transaction->getKey();

        DB::transaction(function () use ($transaction, $account, $ledger): void {
            $ledger->record($account, 'delete_transaction', 'deleted', $transaction);
            $transaction->delete();
        });

        return Response::structured([
            'deleted' => true,
            'id' => $transactionId,
        ]);
    }
}
