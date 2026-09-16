<?php

namespace App\Filament\Resources\DailyTransactions;

use App\Enums\Month;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Filament\Concerns\VisibleToNonAdmins;
use App\Filament\Resources\DailyTransactions\Pages\ManageDailyTransactions;
use App\Models\AccountPlan;
use App\Models\DailyTransaction;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class DailyTransactionResource extends Resource
{
    use VisibleToNonAdmins;

    protected static ?string $model = DailyTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $navigationLabel = 'Lançamentos';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(self::formComponents());
    }

    /**
     * Shared form components, reused by the resource and the thermometer quick-create action.
     *
     * @return array<int, Component>
     */
    public static function formComponents(): array
    {
        return [
            DatePicker::make('date')
                ->label('Data')
                ->required()
                ->default(fn (): string => now()->toDateString()),
            Select::make('type')
                ->label('Tipo')
                ->options(collect(TransactionType::cases())
                    ->mapWithKeys(fn (TransactionType $type): array => [$type->value => $type->label()])
                    ->all())
                ->required(),
            TextInput::make('amount')
                ->label('Valor (R$)')
                ->numeric()
                ->prefix('R$')
                ->minValue(0.01)
                ->rules(['gt:0'])
                ->required(),
            TextInput::make('description')
                ->label('Descrição')
                ->maxLength(255),
            Select::make('account_plan_id')
                ->label('Plano (opcional)')
                ->options(fn (): array => AccountPlan::query()
                    ->where('is_active', true)
                    ->pluck('description', 'id')
                    ->all())
                ->searchable()
                ->preload(),
            Select::make('tags')
                ->label('Tags')
                ->relationship('tags', 'name')
                ->multiple()
                ->searchable()
                ->preload()
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255)
                        ->unique('tags', 'name', modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('user_id', auth()->id())),
                ])
                ->createOptionUsing(fn (array $data): int => Tag::create($data)->getKey()),
            Select::make('status')
                ->label('Status')
                ->options(collect(TransactionStatus::cases())
                    ->mapWithKeys(fn (TransactionStatus $status): array => [$status->value => $status->label()])
                    ->all())
                ->default(TransactionStatus::Realized->value)
                ->required(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (TransactionType $state): string => $state->label())
                    ->color(fn (TransactionType $state): string => $state === TransactionType::Income ? 'success' : 'danger'),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (TransactionStatus $state): string => $state->label())
                    ->color(fn (TransactionStatus $state): string => match ($state) {
                        TransactionStatus::Pending => 'warning',
                        TransactionStatus::Realized => 'success',
                        TransactionStatus::Skipped => 'gray',
                    }),
                TextColumn::make('is_recurring')
                    ->label('Origem')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Recorrente' : 'Manual')
                    ->color(fn (bool $state): string => $state ? 'info' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(TransactionStatus::cases())
                        ->mapWithKeys(fn (TransactionStatus $status): array => [$status->value => $status->label()])
                        ->all()),
                TernaryFilter::make('is_recurring')
                    ->label('Origem')
                    ->trueLabel('Recorrente')
                    ->falseLabel('Manual'),
                Filter::make('year')
                    ->label('Ano')
                    ->form([
                        Select::make('year')
                            ->label('Ano')
                            ->options(fn (): array => DailyTransaction::years())
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['year'] ?? null,
                        fn (Builder $query, int|string $year): Builder => $query->whereYear('date', $year),
                    )),
                Filter::make('month')
                    ->label('Mês')
                    ->form([
                        Select::make('month')
                            ->label('Mês')
                            ->options(Month::options())
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['month'] ?? null,
                        fn (Builder $query, int|string $month): Builder => $query->whereMonth('date', $month),
                    )),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('confirm')
                    ->label('Confirmar')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (DailyTransaction $record): bool => $record->status === TransactionStatus::Pending)
                    ->requiresConfirmation()
                    ->action(fn (DailyTransaction $record): bool => $record->update(['status' => TransactionStatus::Realized])),
                Action::make('skip')
                    ->label('Pular')
                    ->icon(Heroicon::OutlinedForward)
                    ->color('gray')
                    ->visible(fn (DailyTransaction $record): bool => $record->status === TransactionStatus::Pending)
                    ->requiresConfirmation()
                    ->modalHeading('Pular lançamento')
                    ->action(fn (DailyTransaction $record): bool => $record->update(['status' => TransactionStatus::Skipped])),
                DeleteAction::make()
                    ->label('Excluir'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDailyTransactions::route('/'),
        ];
    }
}
