<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Models\UserInitialBalance;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetInitialBalanceTool extends AccountTool
{
    use SerializesDomainRecords;

    protected string $name = 'get_initial_balance';

    protected string $title = 'Consultar saldo inicial';

    protected string $description = 'Retorna o saldo inicial da conta fixa, sua data base e a data em que o cálculo começa.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request, ConfiguredAccount $accounts): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $balance = UserInitialBalance::query()->forUser($account)->first();

        if (! $balance instanceof UserInitialBalance) {
            return Response::structured([
                'has_balance' => false,
                'balance' => null,
            ]);
        }

        return Response::structured([
            'has_balance' => true,
            'balance' => $this->initialBalanceRecord($balance),
        ]);
    }
}
