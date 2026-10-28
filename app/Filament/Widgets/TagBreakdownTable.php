<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithSpendingReport;
use App\Filament\Widgets\Concerns\VisibleToNonAdminWidgets;
use App\Services\SpendingReport;
use Carbon\CarbonImmutable;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Livewire\WithPagination;

class TagBreakdownTable extends Widget
{
    use InteractsWithPageFilters;
    use InteractsWithSpendingReport;
    use VisibleToNonAdminWidgets;
    use WithPagination;

    public const PER_PAGE = 25;

    public const PAGE_NAME = 'tagsPage';

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.tag-breakdown-table';

    protected int|string|array $columnSpan = 'full';

    public function updatedPageFilters(): void
    {
        $this->resetPage(self::PAGE_NAME);
    }

    /**
     * @return array{rows: LengthAwarePaginator<int, array{id: int|null, name: string, color: string, dot: string, is_active: bool, total: string, percentage: string, count: int, average: string, last_date: string}>, total: int}
     */
    protected function getViewData(): array
    {
        $user = $this->reportUser();

        $rows = $user === null
            ? []
            : app(SpendingReport::class)->tags($user, $this->reportPeriod());

        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) $this->getPage(self::PAGE_NAME)), $lastPage);

        $paginator = new LengthAwarePaginator(
            items: array_map(
                fn (array $row): array => [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'color' => $row['color']?->getColor() ?? 'gray',
                    'dot' => $this->tagDot($row['color']?->getColor()),
                    'is_active' => $row['is_active'],
                    'total' => $this->formatMoney($row['total']),
                    'percentage' => number_format($row['percentage'], 1, ',', '.').'%',
                    'count' => $row['count'],
                    'average' => $this->formatMoney($row['average']),
                    'last_date' => $row['last_date'] === null
                        ? '—'
                        : CarbonImmutable::parse($row['last_date'])->format('d/m/Y'),
                ],
                array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            ),
            total: $total,
            perPage: self::PER_PAGE,
            currentPage: $page,
            options: ['path' => Paginator::resolveCurrentPath(), 'pageName' => self::PAGE_NAME],
        );

        return ['rows' => $paginator, 'total' => $total];
    }

    /**
     * Hex used by the color dot. The badge color name comes from TagColor.
     */
    private function tagDot(?string $filamentColor): string
    {
        if ($filamentColor === null) {
            return '#9ca3af';
        }

        $palette = FilamentColor::getColor($filamentColor);

        if ($palette === null) {
            return '#9ca3af';
        }

        return Color::convertToHex((string) ($palette[500] ?? $palette[400]));
    }
}
