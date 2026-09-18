<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\VisibleToNonAdmins;
use App\Models\DailyForecast;
use App\Models\User;
use App\Services\DailyForecastCalculator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;

class DailyForecastPage extends Page
{
    use VisibleToNonAdmins;

    protected string $view = 'filament.pages.daily-forecast-page';

    protected static ?string $slug = 'daily-forecast';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Previsão de diário';

    protected static ?string $title = 'Previsão de diário';

    private DailyForecastCalculator $calculator;

    public function boot(DailyForecastCalculator $calculator): void
    {
        $this->calculator = $calculator;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * @return array{items: array<int, array{id: int, description: string, amount: string}>, monthly_total: string, daily_amount: string, divisor_days: int, count: int}
     */
    public function summary(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [
                'items' => [],
                'monthly_total' => '0.00',
                'daily_amount' => '0.00',
                'divisor_days' => DailyForecastCalculator::DEFAULT_DIVISOR_DAYS,
                'count' => 0,
            ];
        }

        $items = DailyForecast::query()->forUser($user)->orderBy('id')->get();
        $divisorDays = $this->calculator->divisorDays($user);
        $monthlyTotal = $this->calculator->monthlyTotal($user);

        return [
            'items' => $items->map(fn (DailyForecast $item): array => [
                'id' => (int) $item->getKey(),
                'description' => $item->description,
                'amount' => $item->amount,
            ])->all(),
            'monthly_total' => $monthlyTotal,
            'daily_amount' => $this->calculator->dailyAmountFromTotal((float) $monthlyTotal, $divisorDays),
            'divisor_days' => $divisorDays,
            'count' => $items->count(),
        ];
    }

    public function formatMoney(string $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    public function newItemAction(): Action
    {
        return Action::make('newItem')
            ->label('Nova previsão')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Nova previsão')
            ->modalDescription('Informe um gasto variável do mês. O total é dividido pelo divisor para achar o valor diário.')
            ->modalSubmitActionLabel('Adicionar')
            ->model(DailyForecast::class)
            ->schema(self::formComponents())
            ->action(function (array $data): void {
                if (! auth()->user() instanceof User) {
                    return;
                }

                DailyForecast::create($data);

                Notification::make()
                    ->success()
                    ->title('Previsão adicionada.')
                    ->send();
            });
    }

    public function editItemAction(): Action
    {
        return Action::make('editItem')
            ->label('Editar')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading('Editar previsão')
            ->modalSubmitActionLabel('Salvar')
            ->model(DailyForecast::class)
            ->record(fn (array $arguments): ?DailyForecast => $this->findItem($arguments['item'] ?? null))
            ->visible(fn (array $arguments): bool => $this->findItem($arguments['item'] ?? null) instanceof DailyForecast)
            ->schema(self::formComponents())
            ->fillForm(fn (?DailyForecast $record): array => $record instanceof DailyForecast ? [
                'description' => $record->description,
                'amount' => $record->amount,
            ] : [])
            ->action(function (?DailyForecast $record, array $data): void {
                if (! $record instanceof DailyForecast) {
                    $this->notifyMissingItem();

                    return;
                }

                $record->update($data);

                Notification::make()
                    ->success()
                    ->title('Previsão atualizada.')
                    ->send();
            });
    }

    public function configureDivisorAction(): Action
    {
        return Action::make('configureDivisor')
            ->label('Alterar divisor')
            ->modalHeading('Divisor de dias')
            ->modalDescription('O total mensal das previsões é dividido por este número de dias.')
            ->modalSubmitActionLabel('Salvar')
            ->schema([
                TextInput::make('divisor_days')
                    ->label('Dias')
                    ->integer()
                    ->required()
                    ->minValue(DailyForecastCalculator::MIN_DIVISOR_DAYS)
                    ->maxValue(DailyForecastCalculator::MAX_DIVISOR_DAYS)
                    ->default(DailyForecastCalculator::DEFAULT_DIVISOR_DAYS),
            ])
            ->fillForm(function (): array {
                $user = auth()->user();

                return [
                    'divisor_days' => $user instanceof User
                        ? $this->calculator->divisorDays($user)
                        : DailyForecastCalculator::DEFAULT_DIVISOR_DAYS,
                ];
            })
            ->action(function (array $data): void {
                $user = auth()->user();

                if (! $user instanceof User) {
                    return;
                }

                $user->forecast_divisor_days = (int) $data['divisor_days'];
                $user->save();

                Notification::make()
                    ->success()
                    ->title('Divisor atualizado.')
                    ->send();
            });
    }

    public function deleteItem(int $item): void
    {
        $record = $this->findItem($item);

        if (! $record instanceof DailyForecast) {
            $this->notifyMissingItem();

            return;
        }

        $record->delete();

        Notification::make()
            ->success()
            ->title('Previsão removida.')
            ->send();
    }

    /**
     * @return array<int, Component>
     */
    private static function formComponents(): array
    {
        return [
            TextInput::make('description')
                ->label('Descrição')
                ->required()
                ->maxLength(255),
            TextInput::make('amount')
                ->label('Valor (R$)')
                ->numeric()
                ->prefix('R$')
                ->minValue(0.01)
                ->rules(['gt:0'])
                ->required(),
        ];
    }

    private function findItem(mixed $id): ?DailyForecast
    {
        $user = auth()->user();

        if (! $user instanceof User || ! is_numeric($id)) {
            return null;
        }

        return DailyForecast::query()->forUser($user)->find((int) $id);
    }

    private function notifyMissingItem(): void
    {
        Notification::make()
            ->danger()
            ->title('Previsão não encontrada.')
            ->send();
    }
}
