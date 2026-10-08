<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Support\ConfiguredAccount;
use App\Services\BalanceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetHorizonTool extends AccountTool
{
    private const MAX_MONTHS = 12;

    protected string $name = 'get_horizon';

    protected string $title = 'Consultar grade do horizonte';

    protected string $description = 'Retorna a grade do horizonte do Termômetro (1 a 12 meses), com os valores por tipo e o saldo projetado por dia, usando o calculador canônico.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()->min(1900)->max(2200)->description('Ano inicial. Padrão: ano atual.'),
            'month' => $schema->integer()->min(1)->max(12)->description('Mês inicial. Padrão: mês atual.'),
            'months' => $schema->integer()->min(1)->max(self::MAX_MONTHS)->description('Meses no horizonte (1 a 12). Padrão: 1.'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, BalanceCalculator $calculator): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'year' => ['sometimes', 'integer', 'min:1900', 'max:2200'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'months' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_MONTHS],
        ]);

        $now = CarbonImmutable::now();
        $year = (int) ($request->get('year') ?? $now->year);
        $month = (int) ($request->get('month') ?? $now->month);
        $months = (int) ($request->get('months') ?? 1);

        return Response::structured([
            'year' => $year,
            'month' => $month,
            'months' => $months,
            'horizon' => $calculator->horizonGrid($account, $year, $month, $months),
        ]);
    }
}
