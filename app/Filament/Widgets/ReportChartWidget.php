<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithSpendingReport;
use App\Filament\Widgets\Concerns\VisibleToNonAdminWidgets;
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
}
