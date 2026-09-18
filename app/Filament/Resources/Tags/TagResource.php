<?php

namespace App\Filament\Resources\Tags;

use App\Filament\Concerns\HasTagColorField;
use App\Filament\Concerns\VisibleToNonAdmins;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class TagResource extends Resource
{
    use HasTagColorField;
    use VisibleToNonAdmins;

    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Tags';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->mutateStateForValidationUsing(fn (?string $state): string => Tag::normalizeNameKey($state ?? ''))
                    ->unique(
                        'tags',
                        'normalized_name',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('user_id', auth()->id()),
                    ),
                self::tagColorField(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['dailyTransactions', 'accountPlans'])
            ->orderBy('normalized_name');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('name')
                    ->label('Nome')
                    ->view('filament.tables.columns.tag-badge')
                    ->searchable(),
                TextColumn::make('is_active')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ativa' : 'Arquivada')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('daily_transactions_count')
                    ->label('Lançamentos')
                    ->numeric(),
                TextColumn::make('account_plans_count')
                    ->label('Planos')
                    ->numeric(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Ativas')
                    ->falseLabel('Arquivadas')
                    ->placeholder('Todas')
                    ->default(true),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('archive')
                    ->label('Arquivar')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('gray')
                    ->visible(fn (Tag $record): bool => $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Arquivar tag')
                    ->modalDescription(fn (Tag $record): string => "{$record->daily_transactions_count} lançamento(s) e {$record->account_plans_count} plano(s) continuarão vinculados.")
                    ->action(fn (Tag $record): bool => $record->archive()),
                Action::make('reactivate')
                    ->label('Reativar')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('success')
                    ->visible(fn (Tag $record): bool => ! $record->is_active)
                    ->action(fn (Tag $record): bool => $record->reactivate()),
                DeleteAction::make()
                    ->visible(fn (Tag $record): bool => (int) $record->daily_transactions_count === 0
                        && (int) $record->account_plans_count === 0)
                    ->failureNotificationTitle('Tag não pode ser excluída')
                    ->failureNotificationBody('Remova os vínculos ou arquive a tag para preservar o histórico.')
                    ->action(function (Tag $record, DeleteAction $action): void {
                        if ($record->hasUsage() || ! $record->delete()) {
                            $action->failure();

                            return;
                        }

                        $action->success();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTags::route('/'),
        ];
    }
}
