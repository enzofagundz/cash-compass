<?php

namespace App\Filament\Resources\DailyTransactions;

use App\Enums\Month;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Filament\Concerns\HasTagSelector;
use App\Filament\Concerns\VisibleToNonAdmins;
use App\Filament\Resources\DailyTransactions\Pages\ManageDailyTransactions;
use App\Models\AccountPlan;
use App\Models\DailyTransaction;
use App\Services\CardItemSet;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class DailyTransactionResource extends Resource
{
    use HasTagSelector;
    use VisibleToNonAdmins;

    protected static ?string $model = DailyTransaction::class;

    protected static ?string $modelLabel = 'lançamento';

    protected static ?string $pluralModelLabel = 'lançamentos';

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
                ->live()
                ->required(),
            TextInput::make('amount')
                ->label('Valor (R$)')
                ->numeric()
                ->prefix('R$')
                ->minValue(0.01)
                ->rules(['gt:0'])
                ->live(onBlur: true)
                ->required(),
            TextInput::make('description')
                ->label('Descrição')
                ->maxLength(255),
            Select::make('account_plan_id')
                ->label('Plano (opcional)')
                ->options(fn (): array => AccountPlan::query()
                    ->selectableFor((int) auth()->id())
                    ->pluck('description', 'id')
                    ->all())
                ->searchable()
                ->preload(),
            self::tagSelector(),
            Select::make('status')
                ->label('Status')
                ->options(collect(TransactionStatus::cases())
                    ->mapWithKeys(fn (TransactionStatus $status): array => [$status->value => $status->label()])
                    ->all())
                ->default(TransactionStatus::Realized->value)
                ->required(),
            self::cardItemsRepeater(),
            Text::make(fn (Get $get): string => self::cardItemsSummary($get))
                ->visible(fn (Get $get): bool => $get('type') === TransactionType::Card->value),
        ];
    }

    /**
     * Purchase lines that detail a card transaction. The set only exists for
     * the card type and has to close exactly with the parent amount.
     */
    public static function cardItemsRepeater(): Repeater
    {
        return Repeater::make('card_items')
            ->label('Itens do cartão')
            ->addActionLabel('Adicionar item')
            ->helperText('Tags do lançamento valem para itens sem tag própria.')
            ->defaultItems(0)
            ->itemLabel(fn (array $state): ?string => filled($state['description'] ?? null)
                ? (string) $state['description']
                : null)
            ->columns(2)
            ->visible(fn (Get $get): bool => $get('type') === TransactionType::Card->value)
            ->dehydrated(false)
            ->validatedWhenNotDehydrated()
            ->saveRelationshipsWhenHidden()
            ->live(onBlur: true)
            ->afterStateHydrated(function (Repeater $component): void {
                $record = $component->getRecord();

                if (! $record instanceof DailyTransaction || ! $record->exists) {
                    return;
                }

                $component->state(app(CardItemSet::class)->formState($record));
            })
            ->schema([
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
                self::tagIdsSelector(),
            ])
            ->validationAttribute('itens do cartão')
            ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                $type = TransactionType::tryFrom((string) $get('type'));
                $items = is_array($value) ? array_values(array_filter($value, 'is_array')) : [];

                if ($type === null) {
                    return;
                }

                try {
                    app(CardItemSet::class)->validate($type, $get('amount'), $items);
                } catch (ValidationException $exception) {
                    $fail((string) (collect($exception->errors())->flatten()->first() ?? 'Itens inválidos.'));
                }
            })
            ->saveRelationshipsUsing(function (Repeater $component, ?DailyTransaction $record, Get $get): void {
                if (! $record instanceof DailyTransaction) {
                    return;
                }

                $state = $component->getState();

                app(CardItemSet::class)->sync(
                    $record,
                    is_array($state) ? array_values(array_filter($state, 'is_array')) : [],
                    $get('amount'),
                );
            });
    }

    public static function cardItemsSummary(Get $get): string
    {
        $set = app(CardItemSet::class);
        $state = $get('card_items');
        $items = is_array($state) ? array_values(array_filter($state, 'is_array')) : [];
        $difference = $set->differenceCents($get('amount'), $items);

        return 'Soma dos itens: '.$set->formatMoney($set->cents($items))
            .' · Diferença para o valor do lançamento: '.$set->formatMoney($difference);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->stackedOnMobile()
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (TransactionType $state): string => $state->label())
                    ->color('gray'),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('card_items_count')
                    ->counts('cardItems')
                    ->label('Itens')
                    ->numeric()
                    ->alignEnd()
                    ->placeholder('—'),
                ViewColumn::make('tags')
                    ->label('Tags')
                    ->view('filament.tables.columns.tag-list'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(fn (TransactionStatus $state): bool => $state === TransactionStatus::Pending)
                    ->formatStateUsing(fn (TransactionStatus $state): string => $state->label())
                    ->color(fn (TransactionStatus $state): string => match ($state) {
                        TransactionStatus::Pending => 'warning',
                        TransactionStatus::Realized, TransactionStatus::Skipped => 'gray',
                    }),
                TextColumn::make('is_recurring')
                    ->label('Origem')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Recorrente' : 'Manual')
                    ->color('gray'),
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
                EditAction::make()->iconButton(),
                Action::make('confirm')
                    ->label('Confirmar')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->iconButton()
                    ->visible(fn (DailyTransaction $record): bool => $record->status === TransactionStatus::Pending)
                    ->requiresConfirmation()
                    ->action(fn (DailyTransaction $record): bool => $record->update(['status' => TransactionStatus::Realized])),
                Action::make('skip')
                    ->label('Pular')
                    ->icon(Heroicon::OutlinedForward)
                    ->color('gray')
                    ->iconButton()
                    ->visible(fn (DailyTransaction $record): bool => $record->status === TransactionStatus::Pending)
                    ->requiresConfirmation()
                    ->modalHeading('Pular lançamento')
                    ->action(fn (DailyTransaction $record): bool => $record->update(['status' => TransactionStatus::Skipped])),
                DeleteAction::make()
                    ->label('Excluir')
                    ->iconButton(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('tags');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDailyTransactions::route('/'),
        ];
    }
}
