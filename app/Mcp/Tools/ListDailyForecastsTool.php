<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Concerns\PaginatesResults;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Models\DailyForecast;
use App\Services\DailyForecastCalculator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListDailyForecastsTool extends AccountTool
{
    use PaginatesResults;
    use SerializesDomainRecords;

    protected string $name = 'list_daily_forecasts';

    protected string $title = 'Listar previsão de diário';

    protected string $description = 'Lista os itens de gasto variável da conta fixa e o resumo da previsão de diário: total mensal, divisor de dias e valor diário.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->min(1)->description('Página (padrão 1).'),
            'per_page' => $schema->integer()->min(1)->max(100)->description('Itens por página (1 a 100, padrão 25).'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, DailyForecastCalculator $calculator): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $paginator = $this->paginate(
            DailyForecast::query()->forUser($account)->orderBy('id'),
            $request,
        );

        $divisorDays = $calculator->divisorDays($account);
        $monthlyTotal = $calculator->monthlyTotal($account);

        return Response::structured([
            'items' => collect($paginator->items())
                ->map(fn (DailyForecast $forecast): array => $this->forecastRecord($forecast))
                ->all(),
            'divisor_days' => $divisorDays,
            'monthly_total' => $monthlyTotal,
            'daily_amount' => $calculator->dailyAmountFromTotal((float) $monthlyTotal, $divisorDays),
            'count' => $paginator->total(),
        ]);
    }
}
