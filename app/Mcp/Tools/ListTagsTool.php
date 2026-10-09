<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Concerns\PaginatesResults;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListTagsTool extends AccountTool
{
    use PaginatesResults;
    use SerializesDomainRecords;

    protected string $name = 'list_tags';

    protected string $title = 'Listar tags';

    protected string $description = 'Lista as tags da conta fixa, com filtro de situação e contagens de vínculos, com paginação.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'is_active' => $schema->boolean()->description('Situação: true para ativas, false para arquivadas.'),
            'page' => $schema->integer()->min(1)->description('Página (padrão 1).'),
            'per_page' => $schema->integer()->min(1)->max(100)->description('Itens por página (1 a 100, padrão 25).'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $query = Tag::query()
            ->forUser($account)
            ->withCount(['dailyTransactions', 'accountPlans'])
            ->orderBy('normalized_name');

        if ($request->get('is_active') !== null) {
            $query->where('is_active', (bool) $request->get('is_active'));
        }

        $paginator = $this->paginate($query, $request);

        return Response::structured([
            ...$this->paginationMeta($paginator),
            'items' => collect($paginator->items())
                ->map(fn (Tag $tag): array => $this->tagRecord($tag))
                ->all(),
        ]);
    }
}
