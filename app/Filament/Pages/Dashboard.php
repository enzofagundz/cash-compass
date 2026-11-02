<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\VisibleToNonAdmins;
use App\Filament\Widgets\ReportKpis;
use App\Filament\Widgets\TopTagsChart;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class Dashboard extends BaseDashboard
{
    use VisibleToNonAdmins;

    protected static string $routePath = '/dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = -3;

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            ReportKpis::class,
            TopTagsChart::class,
        ];
    }

    public function getColumns(): int
    {
        return 1;
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.dashboard-header', [
            'heading' => $this->getHeading(),
            'actions' => $this->getCachedHeaderActions(),
        ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('reports')
                ->label('Abrir relatórios')
                ->icon(Heroicon::OutlinedChartBar)
                ->url(Reports::getUrl()),
        ];
    }
}
