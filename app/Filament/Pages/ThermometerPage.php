<?php

namespace App\Filament\Pages;

use App\Enums\Month;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Filament\Concerns\VisibleToNonAdmins;
use App\Filament\Resources\DailyTransactions\DailyTransactionResource;
use App\Models\DailyTransaction;
use App\Models\DayCheckIn;
use App\Models\User;
use App\Services\BalanceCalculator;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Daily financial spreadsheet across a multi-month horizon.
 *
 * @property-read array<int, array<string, mixed>> $horizon
 * @property-read array<string, bool> $checkIns
 * @property-read array<int, array<string, mixed>> $detailRows
 */
class ThermometerPage extends Page
{
    use VisibleToNonAdmins;

    public const DEFAULT_MONTHS = 12;

    public const MIN_MONTHS = 1;

    public const MAX_MONTHS = 12;

    private const MIN_YEAR = 1900;

    private const MAX_YEAR = 2099;

    protected string $view = 'filament.pages.thermometer-page';

    protected static ?string $slug = 'thermometer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Termômetro';

    protected static ?string $title = 'Termômetro';

    protected static ?int $navigationSort = 1;

    /**
     * @var int|string|null
     */
    #[Url]
    public $year = null;

    /**
     * @var int|string|null
     */
    #[Url]
    public $month = null;

    /**
     * @var int|string|null
     */
    #[Url]
    public $months = null;

    /**
     * Date (Y-m-d) of the cell currently shown in the detail panel.
     */
    public ?string $detailDate = null;

    /**
     * Movement type filter of the detail panel. Null means every type.
     */
    public ?string $detailType = null;

    private BalanceCalculator $balanceCalculator;

    public function boot(BalanceCalculator $balanceCalculator): void
    {
        $this->balanceCalculator = $balanceCalculator;
    }

    public function mount(): void
    {
        $now = CarbonImmutable::now();

        $year = $this->sanitizeYear($this->year);
        $month = $this->sanitizeMonth($this->month);
        $months = $this->sanitizeMonths($this->months);

        $invalid = ($this->year !== null && $year === null)
            || ($this->month !== null && $month === null)
            || ($this->months !== null && $months === null);

        $this->year = $invalid ? (int) $now->year : ($year ?? (int) $now->year);
        $this->month = $invalid ? (int) $now->month : ($month ?? (int) $now->month);
        $this->months = $invalid ? self::DEFAULT_MONTHS : ($months ?? self::DEFAULT_MONTHS);

        if ($invalid) {
            Notification::make()
                ->warning()
                ->title('Período inválido')
                ->body('Mostramos o período atual com o horizonte padrão.')
                ->send();
        }
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /**
     * @return array<int, TransactionType>
     */
    #[Computed]
    public function columns(): array
    {
        return TransactionType::cases();
    }

    /**
     * Monthly horizon, computed once per request.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function horizon(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return $this->balanceCalculator->horizonGrid(
            $user,
            $this->resolvedYear(),
            $this->resolvedMonth(),
            $this->resolvedMonths(),
        );
    }

    /**
     * Checked-in days keyed by date.
     *
     * @return array<string, bool>
     */
    #[Computed]
    public function checkIns(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        [$start, $end] = $this->horizonRange();

        return DayCheckIn::query()
            ->forUser($user)
            ->whereBetween('date', [$start, $end])
            ->get()
            ->mapWithKeys(fn (DayCheckIn $checkIn): array => [$checkIn->date->toDateString() => true])
            ->all();
    }

    #[Computed]
    public function periodLabel(): string
    {
        $start = CarbonImmutable::create($this->resolvedYear(), $this->resolvedMonth(), 1);
        $end = $start->addMonthsNoOverflow($this->resolvedMonths() - 1);

        return $this->shortMonth($start).' – '.$this->shortMonth($end);
    }

    #[Computed]
    public function horizonMonths(): int
    {
        return $this->resolvedMonths();
    }

    #[Computed]
    public function hasInitialBalance(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->initialBalance()->exists();
    }

    public function formatMoney(string $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    public function longDayLabel(string $date): string
    {
        $day = $this->sanitizeDate($date);

        return $day === null ? '' : $this->longDay($day);
    }

    public function detailLabel(): string
    {
        $date = $this->sanitizeDate($this->detailDate);

        if ($date === null) {
            return '';
        }

        return $this->longDay($date);
    }

    public function detailTypeLabel(): string
    {
        $type = $this->sanitizeType($this->detailType);

        return $type?->columnLabel() ?? 'Todos os tipos';
    }

    /**
     * Movements of the selected day, prepared for the detail panel.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function detailRows(): array
    {
        $user = auth()->user();
        $date = $this->sanitizeDate($this->detailDate);

        if (! $user instanceof User || $date === null) {
            return [];
        }

        $type = $this->sanitizeType($this->detailType);

        $query = DailyTransaction::query()
            ->forUser($user)
            ->where('date', $date->toDateString())
            ->with('tags');

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query
            ->orderBy('type')
            ->orderBy('id')
            ->get()
            ->map(fn (DailyTransaction $movement): array => [
                'id' => $movement->getKey(),
                'description' => $movement->description ?: 'Sem descrição',
                'date' => $movement->date->format('d/m'),
                'tags' => $movement->tags->pluck('name')->implode(', '),
                'amount' => 'R$ '.number_format((float) $movement->amount, 2, ',', '.'),
                'type' => $movement->type->label(),
                'status' => $movement->status->label(),
                'status_value' => $movement->status->value,
                'is_recurring' => $movement->is_recurring,
                'counts' => $this->movementCounts($movement),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function monthOptions(): array
    {
        return collect(range(self::MIN_MONTHS, self::MAX_MONTHS))
            ->mapWithKeys(fn (int $months): array => [
                $months => $months === 1 ? '1 mês' : "{$months} meses",
            ])
            ->all();
    }

    public function goToToday(): void
    {
        $now = CarbonImmutable::now();

        $this->year = (int) $now->year;
        $this->month = (int) $now->month;
        $this->closeDetail();
    }

    public function previousMonth(): void
    {
        $this->shiftPeriod(-1);
    }

    public function nextMonth(): void
    {
        $this->shiftPeriod(1);
    }

    public function previousYear(): void
    {
        $this->shiftPeriod(-12);
    }

    public function nextYear(): void
    {
        $this->shiftPeriod(12);
    }

    public function detailPreviousDay(): void
    {
        $this->shiftDetailDay(-1);
    }

    public function detailNextDay(): void
    {
        $this->shiftDetailDay(1);
    }

    public function toggleCheckIn(string $date): void
    {
        $user = auth()->user();
        $day = $this->sanitizeDate($date);

        if (! $user instanceof User || $day === null) {
            return;
        }

        if ($day->greaterThan(CarbonImmutable::now()->startOfDay())) {
            Notification::make()
                ->danger()
                ->title('Não é possível revisar um dia futuro.')
                ->send();

            return;
        }

        DayCheckIn::toggleFor($user, $day);
    }

    public function selectPeriodAction(): Action
    {
        return Action::make('selectPeriod')
            ->label('Selecionar período')
            ->modalHeading('Selecionar período')
            ->modalDescription('Escolha o primeiro mês e quantos meses de horizonte exibir.')
            ->modalSubmitActionLabel('Aplicar')
            ->schema([
                TextInput::make('year')
                    ->label('Ano')
                    ->integer()
                    ->required()
                    ->minValue(self::MIN_YEAR)
                    ->maxValue(self::MAX_YEAR),
                Select::make('month')
                    ->label('Mês')
                    ->options(Month::options())
                    ->required()
                    ->native(false),
                Select::make('months')
                    ->label('Horizonte')
                    ->options($this->monthOptions())
                    ->required()
                    ->native(false),
            ])
            ->fillForm(fn (): array => [
                'year' => $this->resolvedYear(),
                'month' => $this->resolvedMonth(),
                'months' => $this->resolvedMonths(),
            ])
            ->action(function (array $data): void {
                $this->year = (int) $data['year'];
                $this->month = (int) $data['month'];
                $this->months = (int) $data['months'];

                $this->closeDetail();
            });
    }

    public function configureHorizonAction(): Action
    {
        return Action::make('configureHorizon')
            ->label('Configurar horizonte')
            ->modalHeading('Configurar horizonte')
            ->modalDescription('Defina quantos meses de projeção o termômetro exibe.')
            ->modalSubmitActionLabel('Aplicar')
            ->schema([
                Select::make('months')
                    ->label('Horizonte')
                    ->options($this->monthOptions())
                    ->required()
                    ->native(false),
            ])
            ->fillForm(fn (): array => ['months' => $this->resolvedMonths()])
            ->action(function (array $data): void {
                $this->months = (int) $data['months'];

                $this->closeDetail();
            });
    }

    public function createAction(): Action
    {
        return Action::make('create')
            ->label('Novo lançamento')
            ->icon(Heroicon::OutlinedPlus)
            ->model(DailyTransaction::class)
            ->schema(DailyTransactionResource::formComponents())
            ->action(function (array $data, Schema $schema): void {
                $this->persistMovement($data, $schema);

                Notification::make()
                    ->success()
                    ->title('Lançamento criado.')
                    ->send();
            });
    }

    public function addMovementAction(): Action
    {
        return Action::make('addMovement')
            ->label('Adicionar movimentação')
            ->modalHeading(fn (array $arguments): string => 'Adicionar em '.$this->longDay(
                $this->sanitizeDate($arguments['date'] ?? null) ?? CarbonImmutable::now(),
            ))
            ->modalSubmitActionLabel('Salvar')
            ->schema(DailyTransactionResource::formComponents())
            ->model(DailyTransaction::class)
            ->fillForm(fn (array $arguments): array => [
                'date' => $this->sanitizeDate($arguments['date'] ?? null)?->toDateString() ?? now()->toDateString(),
                'type' => ($this->sanitizeType($arguments['type'] ?? null) ?? TransactionType::Expense)->value,
                'status' => TransactionStatus::Realized->value,
            ])
            ->action(function (array $data, Schema $schema): void {
                $this->persistMovement($data, $schema);

                Notification::make()
                    ->success()
                    ->title('Lançamento criado.')
                    ->send();
            });
    }

    public function openCellAction(): Action
    {
        return Action::make('openCell')
            ->slideOver()
            ->modalHeading(fn (): string => trim($this->detailLabel().' · '.$this->detailTypeLabel(), ' ·'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(fn (): View => view('filament.pages.thermometer.movement-detail'))
            ->registerModalActions([
                $this->addMovementAction(),
                $this->editMovementAction(),
                $this->confirmMovementAction(),
                $this->skipMovementAction(),
                $this->deleteMovementAction(),
            ])
            ->mountUsing(function (array $arguments): void {
                $this->detailDate = $this->sanitizeDate($arguments['date'] ?? null)?->toDateString();
                $this->detailType = $this->sanitizeType($arguments['type'] ?? null)?->value;
            });
    }

    public function editMovementAction(): Action
    {
        return Action::make('editMovement')
            ->label('Editar')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading('Editar lançamento')
            ->modalSubmitActionLabel('Salvar')
            ->model(DailyTransaction::class)
            ->record(fn (array $arguments): ?DailyTransaction => $this->findMovement($arguments['movement'] ?? null))
            ->visible(fn (array $arguments): bool => $this->findMovement($arguments['movement'] ?? null) instanceof DailyTransaction)
            ->schema(DailyTransactionResource::formComponents())
            ->fillForm(function (?DailyTransaction $record): array {
                if (! $record instanceof DailyTransaction) {
                    return [];
                }

                return [
                    'date' => $record->date->toDateString(),
                    'type' => $record->type->value,
                    'amount' => $record->amount,
                    'description' => $record->description,
                    'account_plan_id' => $record->account_plan_id,
                    'tags' => $record->tags->pluck('id')->all(),
                    'status' => $record->status->value,
                ];
            })
            ->action(function (?DailyTransaction $record, array $data, Schema $schema): void {
                if (! $record instanceof DailyTransaction) {
                    $this->notifyMissingMovement();

                    return;
                }

                DB::transaction(function () use ($record, $data, $schema): void {
                    $record->update($data);
                    $schema->model($record)->saveRelationships();
                });

                Notification::make()
                    ->success()
                    ->title('Lançamento atualizado.')
                    ->send();
            });
    }

    public function confirmMovementAction(): Action
    {
        return Action::make('confirmMovement')
            ->label('Confirmar')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Confirmar lançamento')
            ->modalDescription('O lançamento passa a contar como realizado na data dele.')
            ->model(DailyTransaction::class)
            ->record(fn (array $arguments): ?DailyTransaction => $this->findMovement($arguments['movement'] ?? null))
            ->visible(fn (array $arguments): bool => $this->findMovement($arguments['movement'] ?? null) instanceof DailyTransaction)
            ->action(function (?DailyTransaction $record): void {
                if (! $record instanceof DailyTransaction) {
                    $this->notifyMissingMovement();

                    return;
                }

                $record->update(['status' => TransactionStatus::Realized]);

                Notification::make()
                    ->success()
                    ->title('Lançamento confirmado.')
                    ->send();
            });
    }

    public function skipMovementAction(): Action
    {
        return Action::make('skipMovement')
            ->label('Pular ocorrência')
            ->icon(Heroicon::OutlinedForward)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Pular ocorrência')
            ->modalDescription('O lançamento deixa de contar no saldo, mas continua registrado para não ser recriado.')
            ->model(DailyTransaction::class)
            ->record(fn (array $arguments): ?DailyTransaction => $this->findMovement($arguments['movement'] ?? null))
            ->visible(fn (array $arguments): bool => $this->findMovement($arguments['movement'] ?? null) instanceof DailyTransaction)
            ->action(function (?DailyTransaction $record): void {
                if (! $record instanceof DailyTransaction) {
                    $this->notifyMissingMovement();

                    return;
                }

                $record->update(['status' => TransactionStatus::Skipped]);

                Notification::make()
                    ->success()
                    ->title('Ocorrência pulada.')
                    ->send();
            });
    }

    public function deleteMovementAction(): Action
    {
        return Action::make('deleteMovement')
            ->label('Excluir')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Excluir lançamento')
            ->modalDescription('Esta ação não pode ser desfeita.')
            ->model(DailyTransaction::class)
            ->record(fn (array $arguments): ?DailyTransaction => $this->findMovement($arguments['movement'] ?? null, onlyManual: true))
            ->visible(fn (array $arguments): bool => $this->findMovement($arguments['movement'] ?? null, onlyManual: true) instanceof DailyTransaction)
            ->action(function (?DailyTransaction $record): void {
                if (! $record instanceof DailyTransaction) {
                    $this->notifyMissingMovement();

                    return;
                }

                $record->delete();

                Notification::make()
                    ->success()
                    ->title('Lançamento excluído.')
                    ->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createAction(),
        ];
    }

    private function shiftPeriod(int $months): void
    {
        $start = CarbonImmutable::create($this->resolvedYear(), $this->resolvedMonth(), 1)
            ->addMonthsNoOverflow($months);

        if ($start->year < self::MIN_YEAR || $start->year > self::MAX_YEAR) {
            return;
        }

        $this->year = $start->year;
        $this->month = $start->month;
        $this->closeDetail();
    }

    private function shiftDetailDay(int $days): void
    {
        $date = $this->sanitizeDate($this->detailDate);

        if ($date === null) {
            return;
        }

        [$start, $end] = $this->horizonRange();
        $target = $date->addDays($days);

        if ($target->toDateString() < $start || $target->toDateString() > $end) {
            return;
        }

        $this->detailDate = $target->toDateString();
    }

    private function closeDetail(): void
    {
        $this->detailDate = null;
        $this->detailType = null;

        if (count($this->mountedActions ?? [])) {
            $this->unmountAction();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistMovement(array $data, Schema $schema): DailyTransaction
    {
        return DB::transaction(function () use ($data, $schema): DailyTransaction {
            $movement = DailyTransaction::create($data);
            $schema->model($movement)->saveRelationships();

            return $movement;
        });
    }

    private function findMovement(mixed $id, bool $onlyManual = false): ?DailyTransaction
    {
        $user = auth()->user();

        if (! $user instanceof User || ! is_numeric($id)) {
            return null;
        }

        $query = DailyTransaction::query()->forUser($user);

        if ($onlyManual) {
            $query->where('is_recurring', false);
        }

        return $query->find((int) $id);
    }

    private function movementCounts(DailyTransaction $movement): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || $movement->status === TransactionStatus::Skipped) {
            return false;
        }

        $baseDate = $user->initialBalance?->base_date;

        if ($baseDate !== null && $movement->date->lt($baseDate)) {
            return false;
        }

        if ($movement->status === TransactionStatus::Pending) {
            return $movement->date->gte(CarbonImmutable::now()->startOfDay());
        }

        return true;
    }

    private function notifyMissingMovement(): void
    {
        Notification::make()
            ->danger()
            ->title('Lançamento não encontrado.')
            ->send();
    }

    private function resolvedYear(): int
    {
        return $this->sanitizeYear($this->year) ?? (int) CarbonImmutable::now()->year;
    }

    private function resolvedMonth(): int
    {
        return $this->sanitizeMonth($this->month) ?? (int) CarbonImmutable::now()->month;
    }

    private function resolvedMonths(): int
    {
        return $this->sanitizeMonths($this->months) ?? self::DEFAULT_MONTHS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function horizonRange(): array
    {
        $start = CarbonImmutable::create($this->resolvedYear(), $this->resolvedMonth(), 1)->startOfDay();
        $end = $start->addMonthsNoOverflow($this->resolvedMonths() - 1)->endOfMonth();

        return [$start->toDateString(), $end->toDateString()];
    }

    private function sanitizeYear(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $year = (int) $value;

        return $year >= self::MIN_YEAR && $year <= self::MAX_YEAR ? $year : null;
    }

    private function sanitizeMonth(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $month = (int) $value;

        return $month >= 1 && $month <= 12 ? $month : null;
    }

    private function sanitizeMonths(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $months = (int) $value;

        return $months >= self::MIN_MONTHS && $months <= self::MAX_MONTHS ? $months : null;
    }

    private function sanitizeDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function sanitizeType(mixed $value): ?TransactionType
    {
        return is_string($value) ? TransactionType::tryFrom($value) : null;
    }

    private function shortMonth(CarbonImmutable $date): string
    {
        return Month::from($date->month)->shortLabel().'/'.$date->year;
    }

    private function longDay(CarbonImmutable $date): string
    {
        return $date->day.' de '.mb_strtolower(Month::from($date->month)->label(), 'UTF-8').' de '.$date->year;
    }
}
