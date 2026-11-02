<?php

namespace App\Filament\Widgets;

use App\Services\SpendingReport;
use Filament\Support\RawJs;

class MonthlyEvolutionChart extends ReportChartWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Evolução mensal';

    protected ?string $description = 'Gasto total de cada mês dentro do período.';

    protected ?string $maxHeight = '280px';

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

        $rows = app(SpendingReport::class)->monthly($user, $this->reportPeriod());

        if (array_sum(array_column($rows, 'total_cents')) === 0) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Gasto por mês',
                    'data' => array_map(fn (array $row): float => (float) $row['total'], $rows),
                    'backgroundColor' => $this->chartColor(),
                    'borderRadius' => 4,
                    'maxBarThickness' => 32,
                ],
            ],
            'labels' => array_map(fn (array $row): string => $row['label'], $rows),
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
                            grid: {
                                display: false,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                maxTicksLimit: 5,
                                callback: (value) => axisMoney(value),
                            },
                        },
                    },
                };
            })()
        JS);
    }
}
