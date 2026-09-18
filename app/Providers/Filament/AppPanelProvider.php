<?php

namespace App\Providers\Filament;

use App\Filament\Pages\DailyForecastPage;
use Filament\Actions\Action;
use Filament\Http\Controllers\RedirectToHomeController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('/')
            ->brandName(fn () => config('app.name'))
            ->login()
            ->registration()
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->userMenuItems([
                Action::make('dailyForecast')
                    ->label('Previsão de diário')
                    ->icon(Heroicon::OutlinedCalculator)
                    ->url(fn (): string => DailyForecastPage::getUrl())
                    ->visible(fn (): bool => auth()->user()?->isAdmin() === false),
            ])
            ->colors([
                'primary' => Color::Amber,
                'red' => Color::Red,
                'orange' => Color::Orange,
                'yellow' => Color::Yellow,
                'green' => Color::Green,
                'teal' => Color::Teal,
                'blue' => Color::Blue,
                'indigo' => Color::Indigo,
                'purple' => Color::Purple,
            ])
            ->viteTheme('resources/css/filament/app/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->authenticatedRoutes(function (Panel $panel): void {
                Route::get('/', RedirectToHomeController::class)
                    ->middleware($panel->getEmailVerifiedMiddleware())
                    ->name('home');
            })
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
