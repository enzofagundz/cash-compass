<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Models\DailyForecast;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class CreateDailyForecastTool extends AccountTool
{
    use SerializesDomainRecords;

    protected string $name = 'create_daily_forecast';

    protected string $title = 'Criar item de previsão de diário';

    protected string $description = 'Cria um item de gasto variável da previsão de diário da conta fixa. Exige uma chave de operação única por conta: repetir a mesma chave com os mesmos argumentos devolve o item anterior sem duplicar; a mesma chave com argumentos diferentes é rejeitada. O valor deve ser maior que zero.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'operation_key' => $schema->string()->description('Chave de operação única por conta, para idempotência.')->required(),
            'description' => $schema->string()->description('Descrição do gasto variável (até 255 caracteres).')->required(),
            'amount' => $schema->number()->description('Valor exato, maior que zero, com até duas casas decimais.')->required(),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $validated = $request->validate([
            'operation_key' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);

        $attributes = [
            'description' => $validated['description'],
            'amount' => number_format((float) $validated['amount'], 2, '.', ''),
        ];

        $result = $ledger->run(
            $account,
            'create_daily_forecast',
            $validated['operation_key'],
            $attributes,
            function () use ($account, $ledger, $attributes): array {
                $forecast = new DailyForecast($attributes);
                $forecast->user_id = $account->getKey();
                $forecast->save();

                $ledger->record($account, 'create_daily_forecast', 'created', $forecast);

                return ['forecast' => $this->forecastRecord($forecast)];
            },
        );

        return Response::structured($result);
    }
}
