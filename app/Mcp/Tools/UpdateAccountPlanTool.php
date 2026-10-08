<?php

namespace App\Mcp\Tools;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionType;
use App\Mcp\AccountTool;
use App\Mcp\Concerns\ResolvesTransactionRelations;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Models\AccountPlan;
use App\Services\RecurrenceGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdateAccountPlanTool extends AccountTool
{
    use ResolvesTransactionRelations;
    use SerializesDomainRecords;

    protected string $name = 'update_account_plan';

    protected string $title = 'Editar plano de contas';

    protected string $description = 'Edita campos de agendamento e tags de um plano de contas da conta fixa. Alterações de agendamento cancelam as ocorrências pendentes geradas e as regeram pelo observer existente, preservando os lançamentos realizados. Ação de efeito amplo: o Hermes deve explicar o impacto e pedir confirmação na conversa antes de alterar recorrência que cancela ou regenera ocorrências. Não altera a situação ativo/inativo (use activate_account_plan/deactivate_account_plan) nem aceita identidade.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador do plano.')->required(),
            'type' => $schema->string()->enum($this->typeValues())->description('Novo tipo (sem Diário).'),
            'description' => $schema->string()->description('Nova descrição (até 255 caracteres).'),
            'expected_amount' => $schema->number()->description('Novo valor esperado exato, maior que zero, com até duas casas decimais.'),
            'frequency' => $schema->string()->enum($this->frequencyValues())->description('Nova frequência.'),
            'interval' => $schema->integer()->min(1)->description('Novo intervalo.'),
            'day_of_month' => $schema->integer()->min(1)->max(31)->description('Novo dia do mês, ou nulo para usar o dia de início.'),
            'day_of_week' => $schema->integer()->min(0)->max(6)->description('Novo dia da semana (0 domingo a 6 sábado), ou nulo.'),
            'starts_at' => $schema->string()->description('Novo início (AAAA-MM-DD).'),
            'ends_at' => $schema->string()->description('Novo fim (AAAA-MM-DD), ou nulo.'),
            'occurrences' => $schema->integer()->min(1)->description('Novo número de ocorrências, ou nulo.'),
            'tags' => $schema->array()->items($schema->integer())->description('Nova lista de tags da conta; tags arquivadas já vinculadas são preservadas.'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'id' => ['required', 'integer'],
            'type' => ['sometimes', Rule::in($this->typeValues())],
            'description' => ['sometimes', 'string', 'max:255'],
            'expected_amount' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,2'],
            'frequency' => ['sometimes', Rule::enum(RecurrenceFrequency::class)],
            'interval' => ['sometimes', 'integer', 'min:1'],
            'day_of_month' => ['sometimes', 'nullable', 'integer', 'between:1,31'],
            'day_of_week' => ['sometimes', 'nullable', 'integer', 'between:0,6'],
            'starts_at' => ['sometimes', 'date_format:Y-m-d'],
            'ends_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'occurrences' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer'],
        ]);

        $plan = AccountPlan::query()
            ->forUser($account)
            ->find((int) $request->get('id'));

        if (! $plan instanceof AccountPlan) {
            return Response::error('Plano de contas não encontrado.');
        }

        $input = $request->all();
        $changes = [];

        foreach (['type', 'description', 'frequency', 'interval', 'day_of_month', 'day_of_week', 'starts_at', 'ends_at', 'occurrences'] as $field) {
            if (array_key_exists($field, $input)) {
                $changes[$field] = $input[$field];
            }
        }

        if (array_key_exists('expected_amount', $input)) {
            $changes['expected_amount'] = number_format((float) $input['expected_amount'], 2, '.', '');
        }

        $syncTags = array_key_exists('tags', $input);
        $tagIds = $syncTags
            ? $this->resolveTagIds($account, $request, $plan->tags()->pluck('tags.id')->all())
            : [];

        if ($changes === [] && ! $syncTags) {
            throw ValidationException::withMessages([
                'id' => 'Informe ao menos um campo para atualizar.',
            ]);
        }

        DB::transaction(function () use ($plan, $changes, $syncTags, $tagIds, $account, $ledger): void {
            if ($changes !== []) {
                $plan->update($changes);
            }

            if ($syncTags) {
                $plan->tags()->sync($tagIds);
            }

            // Keep pending recurring occurrences mirroring the plan tags.
            app(RecurrenceGenerator::class)->generate($plan);

            $ledger->record($account, 'update_account_plan', 'updated', $plan);
        });

        return Response::structured([
            'account_plan' => $this->accountPlanRecord($plan->refresh()->load('tags')),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function typeValues(): array
    {
        return array_map(fn (TransactionType $type): string => $type->value, TransactionType::planCases());
    }

    /**
     * @return array<int, string>
     */
    private function frequencyValues(): array
    {
        return array_map(fn (RecurrenceFrequency $frequency): string => $frequency->value, RecurrenceFrequency::cases());
    }
}
