<?php

namespace App\Filament\Widgets;

use App\Enums\TagColor;
use App\Filament\Widgets\Concerns\InteractsWithSpendingReport;
use App\Filament\Widgets\Concerns\VisibleToNonAdminWidgets;
use App\Services\SpendingReport;
use Filament\Support\Colors\Color;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TopTagsChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use InteractsWithSpendingReport;
    use VisibleToNonAdminWidgets;

    private const MAX_TAGS = 10;

    protected ?string $pollingInterval = null;

    public function updatedPageFilters(): void
    {
        $this->cachedData = null;
        $this->cachedTagRows = null;
    }

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $emptyStateHeading = 'Nenhuma tag com gasto no período.';

    protected ?string $emptyStateDescription = 'Vincule tags aos lançamentos de gasto para ver o ranking.';

    /**
     * @var array<int, array{id: int|null, name: string, color: TagColor|null, is_active: bool, total: string, total_cents: int, count: int, average: string, last_date: string|null, percentage: float, is_untagged: bool}>|null
     */
    private ?array $cachedTagRows = null;

    public function getHeading(): string
    {
        return $this->pageFilters === null
            ? 'Tags líderes do mês'
            : 'Dez tags de maior gasto';
    }

    public function getDescription(): string
    {
        $untagged = collect($this->tagRows())->firstWhere('is_untagged', true);

        $parts = [];

        if ($untagged !== null) {
            $parts[] = 'Sem tag: '.$this->formatMoney($untagged['total']);
        }

        $parts[] = 'A soma das tags pode passar do total gasto, pois um lançamento entra integralmente em cada tag vinculada.';

        return implode(' ', $parts);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = array_values(array_filter(
            $this->tagRows(),
            fn (array $row): bool => ! $row['is_untagged'],
        ));

        $rows = array_slice($rows, 0, self::MAX_TAGS);

        if ($rows === []) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Gasto por tag',
                    'data' => array_map(fn (array $row): float => (float) $row['total'], $rows),
                    'backgroundColor' => array_map(fn (array $row): string => $this->tagColor($row['color']), $rows),
                ],
            ],
            'labels' => array_map(
                fn (array $row): string => $row['name'].($row['is_active'] ? '' : ' (arquivada)'),
                $rows,
            ),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            (() => {
                const money = (value) => 'R$ ' + Number(value).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });

                return {
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => money(context.raw),
                            },
                        },
                    },
                    scales: {
                        x: {
                            ticks: {
                                callback: (value) => money(value),
                            },
                        },
                        y: {
                            reverse: true,
                        },
                    },
                };
            })()
        JS);
    }

    /**
     * @return array<int, array{id: int|null, name: string, color: TagColor|null, is_active: bool, total: string, total_cents: int, count: int, average: string, last_date: string|null, percentage: float, is_untagged: bool}>
     */
    private function tagRows(): array
    {
        if ($this->cachedTagRows !== null) {
            return $this->cachedTagRows;
        }

        $user = $this->reportUser();

        if ($user === null) {
            return [];
        }

        return $this->cachedTagRows = app(SpendingReport::class)->tags($user, $this->reportPeriod());
    }

    private function tagColor(?TagColor $color): string
    {
        $palette = match ($color) {
            TagColor::Red => Color::Red,
            TagColor::Orange => Color::Orange,
            TagColor::Yellow => Color::Yellow,
            TagColor::Green => Color::Green,
            TagColor::Teal => Color::Teal,
            TagColor::Blue => Color::Blue,
            TagColor::Indigo => Color::Indigo,
            TagColor::Purple => Color::Purple,
            default => Color::Gray,
        };

        return Color::convertToHex($palette[500]);
    }
}
