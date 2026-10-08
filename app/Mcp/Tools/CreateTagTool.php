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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class CreateTagTool extends AccountTool
{
    use SerializesDomainRecords;

    protected string $name = 'create_tag';

    protected string $title = 'Criar tag';

    protected string $description = 'Cria uma tag na conta fixa. O nome é normalizado (espaços colapsados) e a unicidade por conta inclui tags arquivadas. A cor deve pertencer à paleta existente. Exige uma chave de operação única por conta: repetir a mesma chave com os mesmos argumentos devolve a tag anterior sem duplicar; a mesma chave com argumentos diferentes é rejeitada. Não aceita identidade.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'operation_key' => $schema->string()->description('Chave de operação única por conta, para idempotência.')->required(),
            'name' => $schema->string()->description('Nome da tag (até 255 caracteres).')->required(),
            'color' => $schema->string()->enum($this->colorValues())->description('Cor da paleta (padrão: neutro).'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $validated = $request->validate([
            'operation_key' => ['required', 'string', 'max:191'],
            'name' => ['required', 'string', 'max:255'],
            'color' => ['sometimes', Rule::enum(TagColor::class)],
        ]);

        $name = Tag::normalizeName($validated['name']);
        $color = TagColor::from($validated['color'] ?? TagColor::Neutral->value);

        $result = $ledger->run(
            $account,
            'create_tag',
            $validated['operation_key'],
            ['name' => $name, 'color' => $color->value],
            function () use ($account, $ledger, $name, $color): array {
                $tag = new Tag(['name' => $name, 'color' => $color]);
                $tag->user_id = $account->getKey();

                try {
                    $tag->save();
                } catch (UniqueConstraintViolationException) {
                    throw $this->duplicateName();
                }

                $ledger->record($account, 'create_tag', 'created', $tag);

                return ['tag' => $this->tagRecord($tag)];
            },
        );

        return Response::structured($result);
    }

    private function duplicateName(): ValidationException
    {
        return ValidationException::withMessages([
            'name' => 'Já existe uma tag com esse nome nesta conta.',
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
