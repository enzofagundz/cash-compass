<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Models\AccountPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetAccountPlanTool extends AccountTool
{
    use SerializesDomainRecords;

    protected string $name = 'get_account_plan';

    protected string $title = 'Consultar plano de contas';

    protected string $description = 'Retorna um plano de contas da conta fixa pelo identificador, incluindo suas tags.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador do plano.')->required(),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $plan = AccountPlan::query()
            ->forUser($account)
            ->with(['tags' => fn (Relation $query) => $query->where('tags.user_id', $account->getKey())])
            ->find((int) $request->get('id'));

        if (! $plan instanceof AccountPlan) {
            return Response::error('Plano de contas não encontrado.');
        }

        return Response::structured([
            'account_plan' => $this->accountPlanRecord($plan),
        ]);
    }
}
