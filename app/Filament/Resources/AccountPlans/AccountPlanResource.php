<?php

namespace App\Filament\Resources\AccountPlans;

use App\Enums\RecurrenceFrequency;
use App\Filament\Resources\AccountPlans\Pages\ManageAccountPlans;
use App\Models\AccountPlan;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AccountPlanResource extends Resource
{
    protected static ?string $model = AccountPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $navigationLabel = 'Plano de contas';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'income' => 'Entrada',
                        'expense' => 'Saída',
                    ])
                    ->required(),
                TextInput::make('description')
                    ->label('Descrição')
                    ->required()
                    ->maxLength(255),
                TextInput::make('expected_amount')
                    ->label('Valor esperado (R$)')
                    ->numeric()
                    ->prefix('R$')
                    ->required(),
                Select::make('frequency')
                    ->label('Frequência')
                    ->options(collect(RecurrenceFrequency::cases())
                        ->mapWithKeys(fn (RecurrenceFrequency $frequency): array => [
                            $frequency->value => match ($frequency) {
                                RecurrenceFrequency::Daily => 'Diária',
                                RecurrenceFrequency::Weekly => 'Semanal',
                                RecurrenceFrequency::Biweekly => 'Quinzenal',
                                RecurrenceFrequency::Monthly => 'Mensal',
                                RecurrenceFrequency::Yearly => 'Anual',
                                RecurrenceFrequency::Installment => 'Parcelada',
                            },
                        ])
                        ->all())
                    ->required()
                    ->live(),
                TextInput::make('interval')
                    ->label('Intervalo')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required(),
                TextInput::make('day_of_month')
                    ->label('Dia do mês')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(31)
                    ->visible(fn (Get $get): bool => in_array($get('frequency'), [
                        RecurrenceFrequency::Monthly->value,
                        RecurrenceFrequency::Yearly->value,
                        RecurrenceFrequency::Installment->value,
                    ], true)),
                Select::make('day_of_week')
                    ->label('Dia da semana')
                    ->options([
                        0 => 'Domingo',
                        1 => 'Segunda',
                        2 => 'Terça',
                        3 => 'Quarta',
                        4 => 'Quinta',
                        5 => 'Sexta',
                        6 => 'Sábado',
                    ])
                    ->visible(fn (Get $get): bool => in_array($get('frequency'), [
                        RecurrenceFrequency::Weekly->value,
                        RecurrenceFrequency::Biweekly->value,
                    ], true)),
                DatePicker::make('starts_at')
                    ->label('Início')
                    ->required(),
                DatePicker::make('ends_at')
                    ->label('Fim'),
                TextInput::make('occurrences')
                    ->label('Nº de parcelas')
                    ->numeric()
                    ->minValue(1)
                    ->visible(fn (Get $get): bool => $get('frequency') === RecurrenceFrequency::Installment->value),
                Toggle::make('is_active')
                    ->label('Ativo')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'income' ? 'Entrada' : 'Saída'),
                TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable(),
                TextColumn::make('expected_amount')
                    ->label('Valor esperado')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('frequency')
                    ->label('Frequência')
                    ->badge(),
                TextColumn::make('starts_at')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'income' => 'Entrada',
                        'expense' => 'Saída',
                    ]),
                SelectFilter::make('frequency')
                    ->label('Frequência')
                    ->options(collect(RecurrenceFrequency::cases())
                        ->mapWithKeys(fn (RecurrenceFrequency $frequency): array => [$frequency->value => $frequency->value])
                        ->all()),
                TernaryFilter::make('is_active')
                    ->label('Ativo'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->action(function (AccountPlan $record): void {
                        if ($record->hasPastRealizedTransactions()) {
                            Notification::make()
                                ->danger()
                                ->title('Plano não pode ser excluído')
                                ->body('Existem lançamentos realizados no passado vinculados a este plano. Desative-o em vez de excluir.')
                                ->send();

                            return;
                        }

                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title('Plano excluído')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAccountPlans::route('/'),
        ];
    }
}
