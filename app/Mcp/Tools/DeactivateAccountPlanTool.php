<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Models\AccountPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class DeactivateAccountPlanTool extends AccountTool
{
    use SerializesDomainRecords;

    protected string $name = 'deactivate_account_plan';

    protected string $title = 'Desativar plano de contas';

    protected string $description = 'Desativa um plano de contas da conta fixa. O observer existente cancela as ocorrências pendentes futuras e preserva os lançamentos realizados, sem criar nova regra de recorrência. Não aceita identidade.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador do plano.')->required(),
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

        DB::transaction(function () use ($plan, $account, $ledger): void {
            $plan->update(['is_active' => false]);
            $ledger->record($account, 'deactivate_account_plan', 'deactivated', $plan);
        });

        return Response::structured([
            'account_plan' => $this->accountPlanRecord($plan->refresh()->load('tags')),
        ]);
    }
}
