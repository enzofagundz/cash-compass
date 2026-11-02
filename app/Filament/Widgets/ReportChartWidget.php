<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithSpendingReport;
use App\Filament\Widgets\Concerns\VisibleToNonAdminWidgets;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Shared base for report charts: page filters in, polling off, non-admin only.
 */
abstract class ReportChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    use InteractsWithSpendingReport;
    use VisibleToNonAdminWidgets;

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    public function updatedPageFilters(): void
    {
        $this->cachedData = null;
    }

    /**
     * Wrap a chart option body in an IIFE that exposes the shared `theme`
     * object, so each chart only declares what it adds on top of the
     * foundation look (Archivo labels, ink axes and tooltip, warm grid).
     */
    protected function chartOptions(string $body): RawJs
    {
        return RawJs::make(<<<JS
            (() => {
                const theme = {$this->chartTheme()};

                {$body}
            })()
            JS);
    }

    /**
     * Shared Chart.js theme. Horizontal charts override the grid display of
     * each axis; everything else comes from here.
     */
    protected function chartTheme(): string
    {
        return <<<'JS'
            {
                color: '#16130f',
                borderColor: '#16130f',
                borderWidth: 2,
                borderRadius: 0,
                font: {
                    family: "'Archivo', ui-sans-serif, system-ui, sans-serif",
                    weight: '600',
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        backgroundColor: '#16130f',
                        titleColor: '#fffdf6',
                        bodyColor: '#fffdf6',
                        borderColor: '#16130f',
                        borderWidth: 2,
                        cornerRadius: 0,
                        padding: 10,
                        displayColors: false,
                    },
                },
                scales: {
                    x: {
                        border: {
                            display: true,
                            color: '#16130f',
                            width: 2,
                        },
                        grid: {
                            display: false,
                            color: 'rgba(22, 19, 15, 0.12)',
                        },
                        ticks: {
                            color: '#16130f',
                        },
                    },
                    y: {
                        border: {
                            display: true,
                            color: '#16130f',
                            width: 2,
                        },
                        grid: {
                            display: true,
                            color: 'rgba(22, 19, 15, 0.12)',
                        },
                        ticks: {
                            color: '#16130f',
                        },
                    },
                },
            }
            JS;
    }
}
