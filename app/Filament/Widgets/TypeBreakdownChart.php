<?php

namespace App\Filament\Widgets;

use App\Services\SpendingReport;
use Filament\Support\RawJs;

class TypeBreakdownChart extends ReportChartWidget
{
    protected ?string $heading = 'Tipos de gasto';

    protected ?string $description = 'Participação de Saída, Diário e Cartão no total do período.';

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
                    'borderRadius' => 4,
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
        return RawJs::make(<<<'JS'
            (() => {
                const money = (value) => 'R$ ' + Number(value).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });

                const axisMoney = (value) => Number(value) >= 1000
                    ? 'R$ ' + (Number(value) / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil'
                    : money(value);

                return {
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                display: false,
                            },
                            ticks: {
                                maxTicksLimit: 4,
                                callback: (value) => axisMoney(value),
                            },
                        },
                        y: {
                            grid: {
                                display: false,
                            },
                        },
                    },
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
                };
            })()
        JS);
    }
}
