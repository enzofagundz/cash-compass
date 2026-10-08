<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Services\DailyForecastCalculator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class SetForecastDivisorTool extends AccountTool
{
    protected string $name = 'set_forecast_divisor';

    protected string $title = 'Alterar divisor da previsão de diário';

    protected string $description = 'Altera o divisor de dias da previsão de diário da conta fixa, entre 1 e 31. O total mensal dos itens é dividido por este número para achar o valor diário. Não aceita identidade.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'divisor_days' => $schema->integer()
                ->min(DailyForecastCalculator::MIN_DIVISOR_DAYS)
                ->max(DailyForecastCalculator::MAX_DIVISOR_DAYS)
                ->description('Divisor de dias (1 a 31).')
                ->required(),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $validated = $request->validate([
            'divisor_days' => [
                'required',
                'integer',
                'min:'.DailyForecastCalculator::MIN_DIVISOR_DAYS,
                'max:'.DailyForecastCalculator::MAX_DIVISOR_DAYS,
            ],
        ]);

        $account->forecast_divisor_days = (int) $validated['divisor_days'];
        $account->save();

        $ledger->record($account, 'set_forecast_divisor', 'updated', $account);

        $calculator = app(DailyForecastCalculator::class);
        $monthlyTotal = $calculator->monthlyTotal($account);

        return Response::structured([
            'divisor_days' => $calculator->divisorDays($account),
            'monthly_total' => $monthlyTotal,
            'daily_amount' => $calculator->dailyAmountFromTotal((float) $monthlyTotal, $calculator->divisorDays($account)),
        ]);
    }
}
