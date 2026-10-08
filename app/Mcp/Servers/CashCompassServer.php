<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ArchiveTagTool;
use App\Mcp\Tools\ConfirmTransactionTool;
use App\Mcp\Tools\CreateTagTool;
use App\Mcp\Tools\CreateTransactionTool;
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
use App\Mcp\Tools\SkipTransactionTool;
use App\Mcp\Tools\UpdateTagTool;
use App\Mcp\Tools\UpdateTransactionTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Cash Compass')]
#[Version('1.0.0')]
#[Instructions('Servidor local das finanças do Cash Compass. Cada chamada usa uma única conta financeira definida por configuração local; ferramentas não aceitam identidade nem seleção de conta. Consulte lançamentos, planos de contas, tags, saldo inicial, previsão de diário, check-ins, saldos e projeções. Gerencie lançamentos individuais com create_transaction, update_transaction, confirm_transaction, skip_transaction e delete_transaction. Criações exigem uma chave de operação por conta. Gerencie tags com create_tag, update_tag, archive_tag, reactivate_tag e delete_tag; a exclusão de tag só é permitida sem vínculos e exige confirmação na conversa. Antes de excluir, pular ou confirmar, descreva o efeito ao usuário e peça confirmação na conversa: essa confirmação é uma proteção comportamental do agente, não uma comprovação de aprovação humana pelo servidor. As mensagens são em pt-BR.')]
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
        CreateTagTool::class,
        UpdateTagTool::class,
        ArchiveTagTool::class,
        ReactivateTagTool::class,
        DeleteTagTool::class,
    ];
}
