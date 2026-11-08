<?php

namespace App\Filament\Widgets;

use App\Enums\TagColor;
use App\Services\SpendingReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;

class TopTagsChart extends ReportChartWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $emptyStateHeading = 'Nenhuma tag com gasto no período.';

    protected ?string $emptyStateDescription = 'Vincule tags aos lançamentos de gasto para ver o ranking.';

    protected string|BackedEnum|null $emptyStateIcon = Heroicon::OutlinedTag;

    protected ?string $maxHeight = '380px';

    /**
     * @var array{tags: array<int, array{id: int|null, name: string, color: TagColor|null, is_active: bool, total: string, total_cents: int, count: int, average: string, last_date: string|null, percentage: float, is_untagged: bool}>, top: array<int, array{id: int|null, name: string, color: TagColor|null, is_active: bool, total: string, total_cents: int, count: int, average: string, last_date: string|null, percentage: float, is_untagged: bool}>, untagged: array{id: int|null, name: string, color: TagColor|null, is_active: bool, total: string, total_cents: int, count: int, average: string, last_date: string|null, percentage: float, is_untagged: bool}|null}|null
     */
    private ?array $cachedTagReport = null;

    public function updatedPageFilters(): void
    {
        parent::updatedPageFilters();

        $this->cachedTagReport = null;
    }

    public function getHeading(): string
    {
        return $this->pageFilters === null
            ? 'Tags líderes do mês'
            : 'Dez tags de maior gasto';
    }

    public function getDescription(): string
    {
        $untagged = $this->tagReport()['untagged'];

        $parts = [];

        if ($untagged !== null) {
            $parts[] = 'Sem tag: '.$this->formatMoney($untagged['total']).'.';
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
        $rows = $this->tagReport()['top'];

        if ($rows === []) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Gasto por tag',
                    'data' => array_map(fn (array $row): float => (float) $row['total'], $rows),
                    'backgroundColor' => array_map(
                        fn (array $row): string => $row['color'] === null
                            ? $this->chartNeutralColor()
                            : $this->tagColorHex($row['color']),
                        $rows,
                    ),
                    'borderRadius' => 0,
                    'borderColor' => $this->chartInk(),
                    'hoverBorderColor' => $this->chartInk(),
                    'maxBarThickness' => 20,
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
        return $this->chartOptions(<<<'JS'
            const money = (value) => 'R$ ' + Number(value).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            const axisMoney = (value) => Number(value) >= 1000
                ? 'R$ ' + (Number(value) / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil'
                : money(value);

            return {
                ...theme,
                indexAxis: 'y',
                scales: {
                    ...theme.scales,
                    x: {
                        ...theme.scales.x,
                        beginAtZero: true,
                        grid: {
                            ...theme.scales.x.grid,
                            display: true,
                        },
                        ticks: {
                            ...theme.scales.x.ticks,
                            maxTicksLimit: 4,
                            callback: (value) => axisMoney(value),
                        },
                    },
                    y: {
                        ...theme.scales.y,
                        grid: {
                            ...theme.scales.y.grid,
                            display: false,
                        },
                    },
                },
                plugins: {
                    ...theme.plugins,
                    tooltip: {
                        ...theme.plugins.tooltip,
                        callbacks: {
                            label: (context) => money(context.raw),
                        },
                    },
                },
            };
            JS);
    }

    /**
     * @return array{tags: array<int, array{id: int|null, name: string, color: TagColor|null, is_active: bool, total: string, total_cents: int, count: int, average: string, last_date: string|null, percentage: float, is_untagged: bool}>, top: array<int, array{id: int|null, name: string, color: TagColor|null, is_active: bool, total: string, total_cents: int, count: int, average: string, last_date: string|null, percentage: float, is_untagged: bool}>, untagged: array{id: int|null, name: string, color: TagColor|null, is_active: bool, total: string, total_cents: int, count: int, average: string, last_date: string|null, percentage: float, is_untagged: bool}|null}
     */
    private function tagReport(): array
    {
        if ($this->cachedTagReport !== null) {
            return $this->cachedTagReport;
        }

        $user = $this->reportUser();

        if ($user === null) {
            return ['tags' => [], 'top' => [], 'untagged' => null];
        }

        return $this->cachedTagReport = app(SpendingReport::class)->tagReport($user, $this->reportPeriod());
    }
}
