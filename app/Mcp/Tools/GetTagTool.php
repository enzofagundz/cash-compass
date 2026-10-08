<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
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
class GetTagTool extends AccountTool
{
    use SerializesDomainRecords;

    protected string $name = 'get_tag';

    protected string $title = 'Consultar tag';

    protected string $description = 'Retorna uma tag da conta fixa pelo identificador, com contagens de vínculos.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador da tag.')->required(),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $tag = Tag::query()
            ->forUser($account)
            ->withCount(['dailyTransactions', 'accountPlans'])
            ->find((int) $request->get('id'));

        if (! $tag instanceof Tag) {
            return Response::error('Tag não encontrada.');
        }

        return Response::structured([
            'tag' => $this->tagRecord($tag),
        ]);
    }
}
