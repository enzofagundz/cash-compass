<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Models\UserInitialBalance;
use App\Services\BalanceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class UpdateInitialBalanceTool extends AccountTool
{
    use SerializesDomainRecords;

    protected string $name = 'update_initial_balance';

    protected string $title = 'Alterar saldo inicial';

    protected string $description = 'Cria ou altera o saldo inicial da conta fixa. Ação de efeito amplo sobre saldos e histórico: o Hermes deve resumir o efeito (valor, data de início e saldo resultante) e pedir confirmação na conversa antes de chamar esta ferramenta. A confirmação conversacional é uma proteção do agente, não uma comprovação de aprovação humana pelo servidor. Exige chave de operação única por conta. Uma base_date vazia faz o cálculo começar na data de criação do registro.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'operation_key' => $schema->string()->description('Chave de operação única por conta, para idempotência.')->required(),
            'amount' => $schema->number()->description('Valor exato do saldo inicial, com até duas casas decimais.')->required(),
            'base_date' => $schema->string()->description('Data de início (AAAA-MM-DD) ou nula para contar a partir da criação do registro.'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $validated = $request->validate([
            'operation_key' => ['required', 'string', 'max:191'],
            'amount' => ['required', 'numeric', 'decimal:0,2'],
            'base_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);

        $existing = UserInitialBalance::query()->forUser($account)->first();
        $input = $request->all();

        $baseDate = array_key_exists('base_date', $input)
            ? $input['base_date']
            : $existing?->base_date?->toDateString();

        $amount = number_format((float) $validated['amount'], 2, '.', '');

        $result = $ledger->run(
            $account,
            'update_initial_balance',
            $validated['operation_key'],
            ['amount' => $amount, 'base_date' => $baseDate],
            function () use ($account, $ledger, $amount, $baseDate, $existing): array {
                $balance = $account->initialBalance()->updateOrCreate([], [
                    'amount' => $amount,
                    'base_date' => $baseDate,
                ]);

                $ledger->record($account, 'update_initial_balance', $existing === null ? 'created' : 'updated', $balance);

                $calculator = app(BalanceCalculator::class);
                $today = CarbonImmutable::now()->toDateString();

                return [
                    'initial_balance' => $this->initialBalanceRecord($balance),
                    'realized' => $calculator->realized($account, $today),
                    'projected' => $calculator->projected($account, $today),
                ];
            },
        );

        return Response::structured($result);
    }
}
