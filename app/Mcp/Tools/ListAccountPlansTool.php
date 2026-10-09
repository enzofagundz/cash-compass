<?php

namespace App\Mcp\Tools;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionType;
use App\Mcp\AccountTool;
use App\Mcp\Concerns\PaginatesResults;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Models\AccountPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListAccountPlansTool extends AccountTool
{
    use PaginatesResults;
    use SerializesDomainRecords;

    protected string $name = 'list_account_plans';

    protected string $title = 'Listar planos de contas';

    protected string $description = 'Lista os planos de contas recorrentes da conta fixa, com filtros de tipo, frequência e situação e com paginação.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum($this->typeValues())->description('Tipo do plano.'),
            'frequency' => $schema->string()->enum($this->frequencyValues())->description('Frequência de recorrência.'),
            'is_active' => $schema->boolean()->description('Situação: true para ativos, false para inativos.'),
            'page' => $schema->integer()->min(1)->description('Página (padrão 1).'),
            'per_page' => $schema->integer()->min(1)->max(100)->description('Itens por página (1 a 100, padrão 25).'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'type' => ['sometimes', Rule::enum(TransactionType::class)],
            'frequency' => ['sometimes', Rule::enum(RecurrenceFrequency::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $query = AccountPlan::query()
            ->forUser($account)
            ->with(['tags' => fn (Relation $query) => $query->where('tags.user_id', $account->getKey())])
            ->orderBy('id');

        if (($type = $request->get('type')) !== null) {
            $query->where('type', $type);
        }

        if (($frequency = $request->get('frequency')) !== null) {
            $query->where('frequency', $frequency);
        }

        if ($request->get('is_active') !== null) {
            $query->where('is_active', (bool) $request->get('is_active'));
        }

        $paginator = $this->paginate($query, $request);

        return Response::structured([
            ...$this->paginationMeta($paginator),
            'items' => collect($paginator->items())
                ->map(fn (AccountPlan $plan): array => $this->accountPlanRecord($plan))
                ->all(),
        ]);
    }
}
