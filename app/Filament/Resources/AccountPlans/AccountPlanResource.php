<?php

namespace App\Filament\Resources\AccountPlans;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionType;
use App\Filament\Concerns\VisibleToNonAdmins;
use App\Filament\Resources\AccountPlans\Pages\ManageAccountPlans;
use App\Models\AccountPlan;
use App\Models\Tag;
use App\Services\RecurrenceGenerator;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class AccountPlanResource extends Resource
{
    use VisibleToNonAdmins;

    protected static ?string $model = AccountPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $navigationLabel = 'Plano de contas';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(self::formComponents());
    }

    /**
     * Shared form components, reused by the resource and the thermometer forecast action.
     *
     * @return array<int, Component>
     */
    public static function formComponents(): array
    {
        return [
            Select::make('type')
                ->label('Tipo')
                ->options(collect(TransactionType::cases())
                    ->mapWithKeys(fn (TransactionType $type): array => [$type->value => $type->label()])
                    ->all())
                ->required(),
            TextInput::make('description')
                ->label('Descrição')
                ->required()
                ->maxLength(255),
            TextInput::make('expected_amount')
                ->label('Valor esperado (R$)')
                ->numeric()
                ->prefix('R$')
                ->minValue(0.01)
                ->rules(['gt:0'])
                ->required(),
            Select::make('frequency')
                ->label('Frequência')
                ->options(collect(RecurrenceFrequency::cases())
                    ->mapWithKeys(fn (RecurrenceFrequency $frequency): array => [
                        $frequency->value => $frequency->label(),
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
                ->helperText('Dias inexistentes caem no último dia do mês (ex.: 31 vira 28/29 em fevereiro).')
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
                ->label('Fim')
                ->helperText('Alternativa ao número de ocorrências.'),
            TextInput::make('occurrences')
                ->label('Nº de ocorrências')
                ->numeric()
                ->minValue(1)
                ->helperText('Deixe vazio para repetir sem limite.'),
            Toggle::make('is_active')
                ->label('Ativo')
                ->default(true),
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
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (TransactionType $state): string => $state->label()),
                TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable(),
                TextColumn::make('expected_amount')
                    ->label('Valor esperado')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('frequency')
                    ->label('Frequência')
                    ->badge()
                    ->formatStateUsing(fn (RecurrenceFrequency $state): string => $state->label()),
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
                    ->options(collect(TransactionType::cases())
                        ->mapWithKeys(fn (TransactionType $type): array => [$type->value => $type->label()])
                        ->all()),
                SelectFilter::make('frequency')
                    ->label('Frequência')
                    ->options(collect(RecurrenceFrequency::cases())
                        ->mapWithKeys(fn (RecurrenceFrequency $frequency): array => [
                            $frequency->value => $frequency->label(),
                        ])
                        ->all()),
                TernaryFilter::make('is_active')
                    ->label('Ativo'),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn (AccountPlan $record): int => app(RecurrenceGenerator::class)->generate($record)),
                DeleteAction::make()
                    ->action(function (AccountPlan $record): void {
                        if ($record->delete() === false) {
                            Notification::make()
                                ->danger()
                                ->title('Plano não pode ser excluído')
                                ->body('Existem lançamentos realizados até hoje vinculados a este plano. Desative-o em vez de excluir.')
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Plano excluído')
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAccountPlans::route('/'),
        ];
    }
}
