<?php

namespace App\Filament\Pages;

use App\Enums\Month;
use App\Filament\Concerns\VisibleToNonAdmins;
use App\Filament\Resources\DailyTransactions\DailyTransactionResource;
use App\Models\DailyTransaction;
use App\Models\User;
use App\Services\BalanceCalculator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * @property-read Schema $form
 * @property-read Table $table
 */
class ThermometerPage extends Page implements HasTable
{
    use InteractsWithTable;
    use VisibleToNonAdmins;

    protected string $view = 'filament.pages.thermometer-page';

    protected static ?string $slug = 'thermometer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Termômetro';

    protected static ?string $title = 'Termômetro';

    protected static ?int $navigationSort = 1;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    private BalanceCalculator $balanceCalculator;

    public function boot(BalanceCalculator $balanceCalculator): void
    {
        $this->balanceCalculator = $balanceCalculator;
    }

    public function mount(): void
    {
        $this->data = [
            'year' => (int) now()->year,
            'month' => (int) now()->month,
        ];
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('year')
                    ->label('Ano')
                    ->options(DailyTransaction::years())
                    ->live()
                    ->native(false),
                Select::make('month')
                    ->label('Mês')
                    ->options(Month::options())
                    ->live()
                    ->native(false),
            ])
            ->columns(2);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedSchema::make('form'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->grid())
            ->paginated(false)
            ->columns([
                TextColumn::make('day')
                    ->label('Dia'),
                TextColumn::make('income')
                    ->label('Entradas realizadas')
                    ->money('BRL'),
                TextColumn::make('expense')
                    ->label('Saídas realizadas')
                    ->money('BRL'),
                TextColumn::make('result')
                    ->label('Resultado do dia')
                    ->money('BRL')
                    ->color(fn (string $state): string => str_starts_with($state, '-') ? 'danger' : 'success'),
                TextColumn::make('balance')
                    ->label('Saldo acumulado')
                    ->money('BRL'),
                TextColumn::make('projection')
                    ->label('Projeção')
                    ->money('BRL')
                    ->placeholder('—'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Novo lançamento')
                ->icon(Heroicon::OutlinedPlus)
                ->model(DailyTransaction::class)
                ->schema(DailyTransactionResource::formComponents())
                ->action(function (array $data, Schema $schema): void {
                    $transaction = DailyTransaction::create($data);
                    $schema->model($transaction)->saveRelationships();

                    Notification::make()
                        ->success()
                        ->title('Lançamento criado.')
                        ->send();

                    $this->resetTable();
                }),
        ];
    }

    /**
     * @return array<int, array{day: int, date: string|null, income: string, expense: string, result: string, balance: string, projection: string|null}>
     */
    public function grid(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return $this->balanceCalculator->monthGrid(
            $user,
            (int) ($this->data['year'] ?? now()->year),
            (int) ($this->data['month'] ?? now()->month),
        );
    }
}
