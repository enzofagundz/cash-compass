<?php

namespace App\Mcp\Tools;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Mcp\AccountTool;
use App\Mcp\Concerns\PaginatesResults;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Models\DailyTransaction;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListTransactionsTool extends AccountTool
{
    use PaginatesResults;
    use SerializesDomainRecords;

    protected string $name = 'list_transactions';

    protected string $title = 'Listar lançamentos';

    protected string $description = 'Lista os lançamentos manuais e recorrentes da conta fixa, com filtros de período, tipo, status, origem, plano e tag e com paginação.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'date_from' => $schema->string()->description('Data inicial inclusiva (AAAA-MM-DD).'),
            'date_to' => $schema->string()->description('Data final inclusiva (AAAA-MM-DD).'),
            'type' => $schema->string()->enum(TransactionType::values())->description('Tipo do lançamento.'),
            'status' => $schema->string()->enum($this->statusValues())->description('Status do lançamento.'),
            'is_recurring' => $schema->boolean()->description('Origem: true para recorrente, false para manual.'),
            'tag_id' => $schema->integer()->description('Identificador de uma tag da conta.'),
            'account_plan_id' => $schema->integer()->description('Identificador de um plano da conta.'),
            'page' => $schema->integer()->min(1)->description('Página (padrão 1).'),
            'per_page' => $schema->integer()->min(1)->max(100)->description('Itens por página (1 a 100, padrão 25).'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d'],
            'type' => ['sometimes', Rule::enum(TransactionType::class)],
            'status' => ['sometimes', Rule::enum(TransactionStatus::class)],
            'is_recurring' => ['sometimes', 'boolean'],
            'tag_id' => ['sometimes', 'integer'],
            'account_plan_id' => ['sometimes', 'integer'],
        ]);

        $query = DailyTransaction::query()
            ->forUser($account)
            ->with(['tags' => fn (Relation $query) => $query->where('tags.user_id', $account->getKey())])
            ->orderByDesc('date')
            ->orderByDesc('id');

        $this->applyFilters($query, $request, $account);

        $paginator = $this->paginate($query, $request);

        return Response::structured([
            ...$this->paginationMeta($paginator),
            'items' => collect($paginator->items())
                ->map(fn (DailyTransaction $transaction): array => $this->transactionRecord($transaction))
                ->all(),
        ]);
    }

    /**
     * @param  Builder<DailyTransaction>  $query
     */
    private function applyFilters(Builder $query, Request $request, User $account): void
    {
        if (($dateFrom = $request->get('date_from')) !== null) {
            $query->where('date', '>=', $dateFrom);
        }

        if (($dateTo = $request->get('date_to')) !== null) {
            $query->where('date', '<=', $dateTo);
        }

        if (($type = $request->get('type')) !== null) {
            $query->where('type', $type);
        }

        if (($status = $request->get('status')) !== null) {
            $query->where('status', $status);
        }

        if ($request->get('is_recurring') !== null) {
            $query->where('is_recurring', (bool) $request->get('is_recurring'));
        }

        if (($planId = $request->get('account_plan_id')) !== null) {
            $query->where('account_plan_id', (int) $planId);
        }

        if (($tagId = $request->get('tag_id')) !== null) {
            $query->whereHas('tags', fn (Builder $query): Builder => $query
                ->where('tags.id', (int) $tagId)
                ->where('tags.user_id', $account->getKey()));
        }
    }
}
