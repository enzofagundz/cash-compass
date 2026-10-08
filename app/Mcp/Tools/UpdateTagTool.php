<?php

namespace App\Mcp\Tools;

use App\Enums\TagColor;
use App\Mcp\AccountTool;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdateTagTool extends AccountTool
{
    use SerializesDomainRecords;

    protected string $name = 'update_tag';

    protected string $title = 'Editar tag';

    protected string $description = 'Edita o nome e/ou a cor de uma tag da conta fixa. O nome é normalizado e a unicidade por conta inclui tags arquivadas. Não altera o estado de arquivamento nem aceita identidade.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador da tag.')->required(),
            'name' => $schema->string()->description('Novo nome (até 255 caracteres).'),
            'color' => $schema->string()->enum($this->colorValues())->description('Nova cor da paleta.'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['sometimes', 'string', 'max:255'],
            'color' => ['sometimes', Rule::enum(TagColor::class)],
        ]);

        $tag = Tag::query()
            ->forUser($account)
            ->find((int) $request->get('id'));

        if (! $tag instanceof Tag) {
            return Response::error('Tag não encontrada.');
        }

        $input = $request->all();
        $changes = [];

        if (array_key_exists('name', $input)) {
            $changes['name'] = Tag::normalizeName((string) $input['name']);
        }

        if (array_key_exists('color', $input)) {
            $changes['color'] = TagColor::from((string) $input['color']);
        }

        if ($changes === []) {
            throw ValidationException::withMessages([
                'id' => 'Informe ao menos um campo para atualizar.',
            ]);
        }

        try {
            DB::transaction(function () use ($tag, $changes, $account, $ledger): void {
                $tag->update($changes);
                $ledger->record($account, 'update_tag', 'updated', $tag);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'Já existe uma tag com esse nome nesta conta.',
            ]);
        }

        return Response::structured([
            'tag' => $this->tagRecord($tag->refresh()),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function colorValues(): array
    {
        return array_map(fn (TagColor $color): string => $color->value, TagColor::cases());
    }
}
