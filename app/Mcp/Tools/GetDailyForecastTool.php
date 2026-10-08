<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Models\DailyForecast;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetDailyForecastTool extends AccountTool
{
    use SerializesDomainRecords;

    protected string $name = 'get_daily_forecast';

    protected string $title = 'Consultar item de previsão';

    protected string $description = 'Retorna um item de gasto variável da previsão de diário da conta fixa pelo identificador.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador do item de previsão.')->required(),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $forecast = DailyForecast::query()->forUser($account)->find((int) $request->get('id'));

        if (! $forecast instanceof DailyForecast) {
            return Response::error('Item de previsão não encontrado.');
        }

        return Response::structured([
            'forecast' => $this->forecastRecord($forecast),
        ]);
    }
}
