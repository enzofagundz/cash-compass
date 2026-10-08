<?php

use App\Mcp\Servers\CashCompassServer;
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
use App\Models\User;

it('registers the read and mutation tools', function () {
    CashCompassServer::tools()->assertRegistered([
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
    ]);
});

it('rejects a call when the account is not configured', function () {
    config()->set('cash_compass.mcp.account_email', null);

    CashCompassServer::tool(GetInitialBalanceTool::class)
        ->assertHasErrors(['não está configurada']);
});

it('rejects a call when the configured account does not exist', function () {
    config()->set('cash_compass.mcp.account_email', 'missing@example.com');

    CashCompassServer::tool(GetInitialBalanceTool::class)
        ->assertHasErrors(['não existe']);
});

it('rejects an inactive account', function () {
    $account = User::factory()->inactive()->create();
    useMcpAccount($account);

    CashCompassServer::tool(GetInitialBalanceTool::class)
        ->assertHasErrors(['está inativa']);
});

it('rejects an administrative account', function () {
    $account = User::factory()->admin()->create();
    useMcpAccount($account);

    CashCompassServer::tool(GetInitialBalanceTool::class)
        ->assertHasErrors(['administrativas']);
});

it('rejects identity arguments in a call', function () {
    $account = User::factory()->create();
    useMcpAccount($account);

    CashCompassServer::tool(GetTransactionTool::class, ['id' => 1, 'user_id' => 999])
        ->assertHasErrors(['identidade']);
});
