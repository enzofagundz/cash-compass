<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Models\DailyForecast;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteDailyForecastTool extends AccountTool
{
    protected string $name = 'delete_daily_forecast';

    protected string $title = 'Excluir item de previsão de diário';

    protected string $description = 'Exclui um item de gasto variável da previsão de diário da conta fixa. Ação destrutiva e irreversível: o Hermes deve pedir confirmação na conversa, resumindo o item e seu efeito na previsão, antes de chamar esta ferramenta. A confirmação conversacional é uma proteção do agente, não uma comprovação de aprovação humana pelo servidor.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador do item de previsão a excluir.')->required(),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $forecast = DailyForecast::query()->forUser($account)->find((int) $request->get('id'));

        if (! $forecast instanceof DailyForecast) {
            return Response::error('Item de previsão não encontrado.');
        }

        $forecastId = (int) $forecast->getKey();

        DB::transaction(function () use ($forecast, $account, $ledger): void {
            $ledger->record($account, 'delete_daily_forecast', 'deleted', $forecast);
            $forecast->delete();
        });

        return Response::structured([
            'deleted' => true,
            'id' => $forecastId,
        ]);
    }
}
