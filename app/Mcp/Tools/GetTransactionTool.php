<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Models\DailyTransaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetTransactionTool extends AccountTool
{
    use SerializesDomainRecords;

    protected string $name = 'get_transaction';

    protected string $title = 'Consultar lançamento';

    protected string $description = 'Retorna um lançamento da conta fixa pelo identificador, incluindo suas tags.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador do lançamento.')->required(),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $transaction = DailyTransaction::query()
            ->forUser($account)
            ->with(['tags' => fn (Relation $query) => $query->where('tags.user_id', $account->getKey())])
            ->find((int) $request->get('id'));

        if (! $transaction instanceof DailyTransaction) {
            return Response::error('Lançamento não encontrado.');
        }

        return Response::structured([
            'transaction' => $this->transactionRecord($transaction),
        ]);
    }
}
