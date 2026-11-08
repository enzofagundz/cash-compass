<?php

namespace App\Filament\Widgets;

use App\Services\SpendingReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;

class TypeBreakdownChart extends ReportChartWidget
{
    protected ?string $heading = 'Tipos de gasto';

    protected ?string $description = 'Participação de Saída, Diário e Cartão no total do período.';

    protected ?string $emptyStateHeading = 'Nenhum gasto no período.';

    protected ?string $emptyStateDescription = 'O gráfico considera Saída, Diário e Cartão.';

    protected string|BackedEnum|null $emptyStateIcon = Heroicon::OutlinedChartBar;

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $user = $this->reportUser();

        if ($user === null) {
            return [];
        }

        $rows = app(SpendingReport::class)->types($user, $this->reportPeriod());

        if (array_sum(array_column($rows, 'total_cents')) === 0) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Gasto por tipo',
                    'data' => array_map(fn (array $row): float => (float) $row['total'], $rows),
                    'backgroundColor' => $this->chartColor(),
                    'borderRadius' => 0,
                    'borderColor' => $this->chartInk(),
                    'hoverBorderColor' => $this->chartInk(),
                    'maxBarThickness' => 24,
                ],
            ],
            'labels' => array_map(
                fn (array $row): string => $row['label'],
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
}
