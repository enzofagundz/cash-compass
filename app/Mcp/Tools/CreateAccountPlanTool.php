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
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class CreateAccountPlanTool extends AccountTool
{
    use ResolvesTransactionRelations;
    use SerializesDomainRecords;

    protected string $name = 'create_account_plan';

    protected string $title = 'Criar plano de contas';

    protected string $description = 'Cria um plano de contas recorrente na conta fixa e gera as ocorrências pendentes pelo fluxo existente. O tipo Diário é exclusivo de lançamentos manuais e não é aceito. Exige uma chave de operação única por conta: repetir a mesma chave com os mesmos argumentos devolve o plano anterior sem duplicar; a mesma chave com argumentos diferentes é rejeitada. Não aceita identidade.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'operation_key' => $schema->string()->description('Chave de operação única por conta, para idempotência.')->required(),
            'type' => $schema->string()->enum($this->typeValues())->description('Tipo do plano (sem Diário).')->required(),
            'description' => $schema->string()->description('Descrição (até 255 caracteres).')->required(),
            'expected_amount' => $schema->number()->description('Valor esperado exato, maior que zero, com até duas casas decimais.')->required(),
            'frequency' => $schema->string()->enum($this->frequencyValues())->description('Frequência de recorrência.')->required(),
            'interval' => $schema->integer()->min(1)->description('Intervalo entre ocorrências (padrão 1).'),
            'day_of_month' => $schema->integer()->min(1)->max(31)->description('Dia do mês para frequências mensal, anual e parcelada.'),
            'day_of_week' => $schema->integer()->min(0)->max(6)->description('Dia da semana (0 domingo a 6 sábado) para frequências semanal e quinzenal.'),
            'starts_at' => $schema->string()->description('Início (AAAA-MM-DD).')->required(),
            'ends_at' => $schema->string()->description('Fim opcional (AAAA-MM-DD), alternativa ao número de ocorrências.'),
            'occurrences' => $schema->integer()->min(1)->description('Número total de ocorrências (parcelada), opcional.'),
            'is_active' => $schema->boolean()->description('Situação inicial (padrão: ativo).'),
            'tags' => $schema->array()->items($schema->integer())->description('Identificadores de tags ativas da conta.'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $validated = $request->validate([
            'operation_key' => ['required', 'string', 'max:191'],
            'type' => ['required', Rule::in($this->typeValues())],
            'description' => ['required', 'string', 'max:255'],
            'expected_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'frequency' => ['required', Rule::enum(RecurrenceFrequency::class)],
            'interval' => ['sometimes', 'integer', 'min:1'],
            'day_of_month' => ['sometimes', 'nullable', 'integer', 'between:1,31'],
            'day_of_week' => ['sometimes', 'nullable', 'integer', 'between:0,6'],
            'starts_at' => ['required', 'date_format:Y-m-d'],
            'ends_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'occurrences' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer'],
        ]);

        $tagIds = $this->resolveTagIds($account, $request);

        $attributes = [
            'type' => $validated['type'],
            'description' => $validated['description'],
            'expected_amount' => number_format((float) $validated['expected_amount'], 2, '.', ''),
            'frequency' => $validated['frequency'],
            'interval' => $validated['interval'] ?? 1,
            'day_of_month' => $validated['day_of_month'] ?? null,
            'day_of_week' => $validated['day_of_week'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'] ?? null,
            'occurrences' => $validated['occurrences'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ];

        $result = $ledger->run(
            $account,
            'create_account_plan',
            $validated['operation_key'],
            [...$attributes, 'tags' => collect($tagIds)->sort()->values()->all()],
            function () use ($account, $ledger, $attributes, $tagIds): array {
                $plan = new AccountPlan($attributes);
                $plan->user_id = $account->getKey();
                $plan->save();

                $plan->tags()->sync($tagIds);

                // The observer generates before the tags exist; regenerate so the
                // pending occurrences mirror the plan tags, as in the panel.
                app(RecurrenceGenerator::class)->generate($plan);

                $ledger->record($account, 'create_account_plan', 'created', $plan);

                return ['account_plan' => $this->accountPlanRecord($plan->load('tags'))];
            },
        );

        return Response::structured($result);
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
