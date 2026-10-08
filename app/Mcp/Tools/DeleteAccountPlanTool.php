<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Models\AccountPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteAccountPlanTool extends AccountTool
{
    protected string $name = 'delete_account_plan';

    protected string $title = 'Excluir plano de contas';

    protected string $description = 'Exclui um plano de contas da conta fixa quando não há lançamentos realizados até hoje. Ação destrutiva e irreversível: o Hermes deve explicar o efeito e pedir confirmação na conversa antes de chamar esta ferramenta. O observer existente cancela as ocorrências pendentes; planos com lançamentos realizados até hoje não podem ser excluídos e devem ser desativados. A confirmação conversacional é uma proteção do agente, não uma comprovação de aprovação humana pelo servidor.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador do plano a excluir.')->required(),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $plan = AccountPlan::query()
            ->forUser($account)
            ->find((int) $request->get('id'));

        if (! $plan instanceof AccountPlan) {
            return Response::error('Plano de contas não encontrado.');
        }

        if ($plan->hasRealizedTransactionsUpToToday()) {
            throw ValidationException::withMessages([
                'id' => 'Plano com lançamentos realizados até hoje não pode ser excluído; desative-o em vez de excluir.',
            ]);
        }

        $planId = (int) $plan->getKey();

        DB::transaction(function () use ($plan, $account, $ledger): void {
            $ledger->record($account, 'delete_account_plan', 'deleted', $plan);

            if ($plan->delete() === false) {
                throw ValidationException::withMessages([
                    'id' => 'Plano com lançamentos realizados até hoje não pode ser excluído; desative-o em vez de excluir.',
                ]);
            }
        });

        return Response::structured([
            'deleted' => true,
            'id' => $planId,
        ]);
    }
}
