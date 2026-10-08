<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Models\DailyForecast;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdateDailyForecastTool extends AccountTool
{
    use SerializesDomainRecords;

    protected string $name = 'update_daily_forecast';

    protected string $title = 'Editar item de previsão de diário';

    protected string $description = 'Edita a descrição ou o valor de um item de gasto variável da previsão de diário da conta fixa. O valor, quando informado, deve ser maior que zero. Não aceita identidade.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Identificador do item de previsão.')->required(),
            'description' => $schema->string()->description('Nova descrição (até 255 caracteres).'),
            'amount' => $schema->number()->description('Novo valor exato, maior que zero, com até duas casas decimais.'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'id' => ['required', 'integer'],
            'description' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);

        $forecast = DailyForecast::query()->forUser($account)->find((int) $request->get('id'));

        if (! $forecast instanceof DailyForecast) {
            return Response::error('Item de previsão não encontrado.');
        }

        $input = $request->all();
        $changes = [];

        if (array_key_exists('description', $input)) {
            $changes['description'] = $input['description'];
        }

        if (array_key_exists('amount', $input)) {
            $changes['amount'] = number_format((float) $input['amount'], 2, '.', '');
        }

        if ($changes === []) {
            throw ValidationException::withMessages([
                'id' => 'Informe ao menos um campo para atualizar.',
            ]);
        }

        $forecast->update($changes);

        $ledger->record($account, 'update_daily_forecast', 'updated', $forecast);

        return Response::structured([
            'forecast' => $this->forecastRecord($forecast->refresh()),
        ]);
    }
}
