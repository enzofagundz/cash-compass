<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithSpendingReport;
use App\Filament\Widgets\Concerns\VisibleToNonAdminWidgets;
use App\Services\SpendingReport;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class LargestTransactionsTable extends Widget
{
    use InteractsWithPageFilters;
    use InteractsWithSpendingReport;
    use VisibleToNonAdminWidgets;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.largest-transactions-table';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array{rows: array<int, array{id: int, date: string, description: string, tags: array<int, string>, amount: string}>}
     */
    protected function getViewData(): array
    {
        $user = $this->reportUser();

        if ($user === null) {
            return ['rows' => []];
        }

        $rows = array_map(
            fn (array $row): array => [
                'id' => $row['id'],
                'date' => CarbonImmutable::parse($row['date'])->format('d/m/Y'),
                'description' => $row['description'],
                'tags' => $row['tags'],
                'amount' => $this->formatMoney($row['amount']),
            ],
            app(SpendingReport::class)->largest($user, $this->reportPeriod()),
        );

        return ['rows' => $rows];
    }
}
