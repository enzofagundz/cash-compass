<?php

namespace App\Mcp\Tools;

use App\Mcp\AccountTool;
use App\Mcp\Support\ConfiguredAccount;
use App\Mcp\Support\MutationLedger;
use App\Models\DayCheckIn;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class SetDayCheckInTool extends AccountTool
{
    protected string $name = 'set_day_check_in';

    protected string $title = 'Definir check-in de um dia';

    protected string $description = 'Define o estado explícito do check-in de um dia da conta fixa: marque checked_in como verdadeiro para revisar o dia ou falso para desmarcar. Datas futuras são rejeitadas. A ferramenta define um estado, não alterna: confira o estado atual com list_day_check_ins antes de repetir uma chamada cujo resultado ficou incerto. Check-ins são independentes dos lançamentos e não aceitam identidade.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()->description('Data do check-in (AAAA-MM-DD). Não pode ser futura.')->required(),
            'checked_in' => $schema->boolean()->description('Verdadeiro para marcar o dia, falso para desmarcar.')->required(),
        ];
    }

    public function handle(Request $request, ConfiguredAccount $accounts, MutationLedger $ledger): Response|ResponseFactory
    {
        $account = $this->account($request, $accounts);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'checked_in' => ['required', 'boolean'],
        ]);

        $day = CarbonImmutable::parse($validated['date'])->startOfDay();

        if ($day->greaterThan(CarbonImmutable::now()->startOfDay())) {
            throw ValidationException::withMessages([
                'date' => 'Não é possível revisar um dia futuro.',
            ]);
        }

        $existed = DayCheckIn::query()
            ->forUser($account)
            ->where('date', $validated['date'])
            ->exists();

        $checkedIn = DayCheckIn::setFor($account, $validated['date'], (bool) $validated['checked_in']);

        if ($checkedIn !== $existed) {
            $ledger->record(
                $account,
                'set_day_check_in',
                $checkedIn ? 'checked_in' : 'unchecked_in',
                DayCheckIn::query()->forUser($account)->where('date', $validated['date'])->first(),
            );
        }

        return Response::structured([
            'date' => $validated['date'],
            'checked_in' => $checkedIn,
            'changed' => $checkedIn !== $existed,
        ]);
    }
}
