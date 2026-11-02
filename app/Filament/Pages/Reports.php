<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\VisibleToNonAdmins;
use App\Filament\Widgets\HourlySpendChart;
use App\Filament\Widgets\LargestTransactionsTable;
use App\Filament\Widgets\MonthlyEvolutionChart;
use App\Filament\Widgets\ReportKpis;
use App\Filament\Widgets\TagBreakdownTable;
use App\Filament\Widgets\TopTagsChart;
use App\Filament\Widgets\TypeBreakdownChart;
use App\Filament\Widgets\WeekdaySpendChart;
use App\Services\SpendingReport;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;

class Reports extends Dashboard
{
    use HasFiltersForm;
    use VisibleToNonAdmins;

    protected static string $routePath = '/reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Relatórios';

    protected static ?string $title = 'Relatórios';

    protected static ?int $navigationSort = 4;

    #[Url]
    public string $activeTab = 'behavior';

    public function mount(): void
    {
        if ($this->filters === null) {
            $this->filters = ['preset' => SpendingReport::CURRENT_MONTH];
        }

        if (! in_array($this->activeTab, ['behavior', 'tags'], true)) {
            $this->activeTab = 'behavior';
        }
    }

    public function getSubheading(): ?string
    {
        return app(SpendingReport::class)->period($this->filters)['label'];
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.reports-header', [
            'heading' => $this->getHeading(),
            'period' => $this->getSubheading(),
        ]);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('preset')
                ->label('Período')
                ->options(SpendingReport::presetOptions())
                ->default(SpendingReport::CURRENT_MONTH)
                ->selectablePlaceholder(false)
                ->live(),
            DatePicker::make('start_date')
                ->label('Data inicial')
                ->displayFormat('d/m/Y')
                ->visible(fn (Get $get): bool => $get('preset') === SpendingReport::CUSTOM)
                ->required(fn (Get $get): bool => $get('preset') === SpendingReport::CUSTOM),
            DatePicker::make('end_date')
                ->label('Data final')
                ->displayFormat('d/m/Y')
                ->visible(fn (Get $get): bool => $get('preset') === SpendingReport::CUSTOM)
                ->required(fn (Get $get): bool => $get('preset') === SpendingReport::CUSTOM),
        ]);
    }

    public function booted(): void
    {
        $this->validatePeriod();
    }

    public function updatedFilters(): void
    {
        if ($this->persistsFiltersInSession()) {
            session()->put($this->getFiltersSessionKey(), $this->filters);
        }

        $this->validatePeriod();
    }

    private function validatePeriod(): void
    {
        if ($this->filters === null) {
            return;
        }

        $start = $this->filters['start_date'] ?? null;
        $end = $this->filters['end_date'] ?? null;

        if (($this->filters['preset'] ?? null) !== SpendingReport::CUSTOM) {
            $this->resetErrorBag('filters.start_date');

            return;
        }

        if (blank($start) || blank($end)) {
            $this->addError('filters.start_date', 'Informe a data inicial e a data final.');

            return;
        }

        try {
            $startDate = CarbonImmutable::parse((string) $start);
            $endDate = CarbonImmutable::parse((string) $end);
        } catch (\Throwable) {
            return;
        }

        if ($startDate->greaterThan($endDate)) {
            $this->addError('filters.start_date', 'A data inicial deve ser anterior ou igual à data final.');

            return;
        }

        $this->resetErrorBag('filters.start_date');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFiltersFormContentComponent(),
            Grid::make(1)->schema(fn (): array => $this->getWidgetsSchemaComponents([
                ReportKpis::class,
            ])),
            Tabs::make()
                ->livewireProperty('activeTab')
                ->tabs([
                    'behavior' => Tab::make('Comportamento')
                        ->icon(Heroicon::OutlinedClock)
                        ->schema([
                            Grid::make(2)->schema(fn (): array => $this->getWidgetsSchemaComponents([
                                HourlySpendChart::class,
                                WeekdaySpendChart::class,
                                TypeBreakdownChart::class,
                                MonthlyEvolutionChart::class,
                                LargestTransactionsTable::class,
                            ])),
                        ]),
                    'tags' => Tab::make('Tags')
                        ->icon(Heroicon::OutlinedTag)
                        ->schema([
                            Grid::make(1)->schema(fn (): array => $this->getWidgetsSchemaComponents([
                                TopTagsChart::class,
                                TagBreakdownTable::class,
                            ])),
                        ]),
                ]),
        ]);
    }
}
