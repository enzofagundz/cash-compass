<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteTagTool extends AccountTool
{
    protected string $name = 'delete_tag';

    protected string $title = 'Excluir tag';

    protected string $description = 'Exclui uma tag sem vínculos da conta fixa. Ação destrutiva e irreversível: o Hermes deve pedir confirmação na conversa, resumindo a tag e seu efeito, antes de chamar esta ferramenta. Tags vinculadas a lançamentos ou planos não podem ser excluídas; arquive-as para preservar o histórico. A confirmação conversacional é uma proteção do agente, não uma comprovação de aprovação humana pelo servidor.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador da tag a excluir.')->required(),
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

        if ($tag->hasUsage()) {
            throw ValidationException::withMessages([
                'id' => 'Tag vinculada a lançamentos ou planos não pode ser excluída; arquive-a para preservar o histórico.',
            ]);
        }

        $tagId = (int) $tag->getKey();

        DB::transaction(function () use ($tag, $account, $ledger): void {
            $ledger->record($account, 'delete_tag', 'deleted', $tag);
            $tag->delete();
        });

        return Response::structured([
            'deleted' => true,
            'id' => $tagId,
        ]);
    }
}
