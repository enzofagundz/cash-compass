<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class ArchiveTagTool extends AccountTool
{
    use SerializesDomainRecords;

    protected string $name = 'archive_tag';

    protected string $title = 'Arquivar tag';

    protected string $description = 'Arquiva uma tag da conta fixa, preservando os vínculos existentes com lançamentos e planos. A tag deixa de aceitar novos vínculos até ser reativada. Não aceita identidade.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador da tag.')->required(),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $tag = Tag::query()
            ->forUser($account)
            ->find((int) $request->get('id'));

        if (! $tag instanceof Tag) {
            return Response::error('Tag não encontrada.');
        }

        DB::transaction(function () use ($tag, $account, $ledger): void {
            $tag->archive();
            $ledger->record($account, 'archive_tag', 'archived', $tag);
        });

        return Response::structured([
            'tag' => $this->tagRecord($tag->refresh()),
        ]);
    }
}
