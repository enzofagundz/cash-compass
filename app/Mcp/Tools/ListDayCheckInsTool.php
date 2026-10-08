<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Concerns\PaginatesResults;
use App\Mcp\Concerns\SerializesDomainRecords;
use App\Mcp\Support\ConfiguredAccount;
use App\Models\DayCheckIn;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListDayCheckInsTool extends AccountTool
{
    use PaginatesResults;
    use SerializesDomainRecords;

    protected string $name = 'list_day_check_ins';

    protected string $title = 'Listar check-ins';

    protected string $description = 'Lista os check-ins de dias da conta fixa, com filtro de período e paginação.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'date_from' => $schema->string()->description('Data inicial inclusiva (AAAA-MM-DD).'),
            'date_to' => $schema->string()->description('Data final inclusiva (AAAA-MM-DD).'),
            'page' => $schema->integer()->min(1)->description('Página (padrão 1).'),
            'per_page' => $schema->integer()->min(1)->max(100)->description('Itens por página (1 a 100, padrão 25).'),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $request->validate([
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $query = DayCheckIn::query()
            ->forUser($account)
            ->orderByDesc('date')
            ->orderByDesc('id');

        if (($dateFrom = $request->get('date_from')) !== null) {
            $query->where('date', '>=', $dateFrom);
        }

        if (($dateTo = $request->get('date_to')) !== null) {
            $query->where('date', '<=', $dateTo);
        }

        $paginator = $this->paginate($query, $request);

        return Response::structured([
            ...$this->paginationMeta($paginator),
            'items' => collect($paginator->items())
                ->map(fn (DayCheckIn $checkIn): array => $this->checkInRecord($checkIn))
                ->all(),
        ]);
    }
}
