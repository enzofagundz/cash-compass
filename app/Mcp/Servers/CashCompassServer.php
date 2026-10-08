<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ArchiveTagTool;
use App\Mcp\Tools\ConfirmTransactionTool;
use App\Mcp\Tools\CreateDailyForecastTool;
use App\Mcp\Tools\CreateTagTool;
use App\Mcp\Tools\CreateTransactionTool;
use App\Mcp\Tools\DeleteDailyForecastTool;
use App\Mcp\Tools\DeleteTagTool;
use App\Mcp\Tools\DeleteTransactionTool;
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
use App\Mcp\Tools\ReactivateTagTool;
use App\Mcp\Tools\SetDayCheckInTool;
use App\Mcp\Tools\SetForecastDivisorTool;
use App\Mcp\Tools\SkipTransactionTool;
use App\Mcp\Tools\UpdateDailyForecastTool;
use App\Mcp\Tools\UpdateInitialBalanceTool;
use App\Mcp\Tools\UpdateTagTool;
use App\Mcp\Tools\UpdateTransactionTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Cash Compass')]
#[Version('1.0.0')]
#[Instructions('Servidor local das finanças do Cash Compass. Cada chamada usa uma única conta financeira definida por configuração local; ferramentas não aceitam identidade nem seleção de conta. Consulte lançamentos, planos de contas, tags, saldo inicial, previsão de diário, check-ins, saldos e projeções. Gerencie lançamentos individuais com create_transaction, update_transaction, confirm_transaction, skip_transaction e delete_transaction. Gerencie tags com create_tag, update_tag, archive_tag, reactivate_tag e delete_tag; a exclusão de tag só é permitida sem vínculos. Gerencie o saldo inicial com update_initial_balance, a previsão de diário com create_daily_forecast, update_daily_forecast, delete_daily_forecast e set_forecast_divisor (1 a 31 dias), e os check-ins com set_day_check_in, que define um estado explícito e rejeita datas futuras. Criações e o saldo inicial exigem uma chave de operação por conta. Antes de excluir, pular, confirmar, alterar o saldo inicial ou excluir uma tag, descreva o efeito ao usuário e peça confirmação na conversa: essa confirmação é uma proteção comportamental do agente, não uma comprovação de aprovação humana pelo servidor. As mensagens são em pt-BR.')]
class CashCompassServer extends Server
{
    public int $defaultPaginationLength = 50;

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
        CreateTransactionTool::class,
        UpdateTransactionTool::class,
        ConfirmTransactionTool::class,
        SkipTransactionTool::class,
        DeleteTransactionTool::class,
        UpdateInitialBalanceTool::class,
        CreateDailyForecastTool::class,
        UpdateDailyForecastTool::class,
        DeleteDailyForecastTool::class,
        SetForecastDivisorTool::class,
        SetDayCheckInTool::class,
        CreateTagTool::class,
        UpdateTagTool::class,
        ArchiveTagTool::class,
        ReactivateTagTool::class,
        DeleteTagTool::class,
    ];
}
