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
class GetBalanceTool extends AccountTool
{
    protected string $name = 'get_balance';

    protected string $title = 'Consultar saldo';

    protected string $description = 'Retorna o saldo realizado e projetado da conta fixa para uma data, usando os calculadores canônicos do painel.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()->description('Data alvo (AAAA-MM-DD). Padrão: hoje.'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, BalanceCalculator $calculator): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $date = (string) ($request->get('date') ?? CarbonImmutable::now()->toDateString());
        $target = CarbonImmutable::parse($date)->toDateString();

        return Response::structured([
            'date' => $target,
            'realized' => $calculator->realized($account, $target),
            'projected' => $calculator->projected($account, $target),
        ]);
    }
}
