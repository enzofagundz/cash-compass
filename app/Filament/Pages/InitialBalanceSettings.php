<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\VisibleToNonAdmins;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class InitialBalanceSettings extends Page
{
    use VisibleToNonAdmins;

    protected string $view = 'filament.pages.initial-balance-settings';

    protected static ?string $slug = 'initial-balance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Wallet;

    protected static ?string $navigationLabel = 'Saldo inicial';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $balance = $user->initialBalance()->first();

        if ($balance === null) {
            $this->form->fill([
                'base_date' => now()->toDateString(),
            ]);

            return;
        }

        $this->form->fill([
            'amount' => $balance->amount,
            'base_date' => $balance->base_date?->format('Y-m-d'),
        ]);
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
                TextInput::make('amount')
                    ->label('Saldo inicial (R$)')
                    ->numeric()
                    ->required()
                    ->prefix('R$'),
                DatePicker::make('base_date')
                    ->label('Data base'),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Salvar')
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $user->saveInitialBalance($data);

        Notification::make()
            ->success()
            ->title('Saldo inicial salvo.')
            ->send();
    }
}
