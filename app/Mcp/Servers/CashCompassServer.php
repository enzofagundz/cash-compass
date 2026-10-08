<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetAccountPlanTool;
use App\Mcp\Tools\GetBalanceTool;
use App\Mcp\Tools\GetDailyForecastTool;
use App\Mcp\Tools\GetHorizonTool;
use App\Mcp\Tools\GetInitialBalanceTool;
use App\Mcp\Tools\GetTagTool;
use App\Mcp\Tools\GetTransactionTool;
use App\Mcp\Tools\ListAccountPlansTool;
use App\Mcp\Tools\ListDailyForecastsTool;
use App\Mcp\Tools\ListDayCheckInsTool;
use App\Mcp\Tools\ListTagsTool;
use App\Mcp\Tools\ListTransactionsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Cash Compass')]
#[Version('1.0.0')]
#[Instructions('Servidor local e somente leitura das finanças do Cash Compass. Cada chamada usa uma única conta financeira definida por configuração local; ferramentas não aceitam identidade nem seleção de conta. Consulte lançamentos, planos de contas, tags, saldo inicial, previsão de diário, check-ins, saldos e projeções. As mensagens são em pt-BR.')]
class CashCompassServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListTransactionsTool::class,
        GetTransactionTool::class,
        ListAccountPlansTool::class,
        GetAccountPlanTool::class,
        ListTagsTool::class,
        GetTagTool::class,
        GetInitialBalanceTool::class,
        ListDailyForecastsTool::class,
        GetDailyForecastTool::class,
        ListDayCheckInsTool::class,
        GetBalanceTool::class,
        GetHorizonTool::class,
    ];
}
