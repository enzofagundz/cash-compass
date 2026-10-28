<?php

namespace App\Filament\Widgets;

use App\Services\SpendingReport;
use Filament\Support\RawJs;

class MonthlyEvolutionChart extends ReportChartWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Evolução mensal';

    protected ?string $description = 'Gasto total de cada mês dentro do período.';

    protected function getType(): string
    {
        return 'line';
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
                    'borderColor' => $this->chartColor(),
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
                        y: {
                            ticks: {
                                callback: (value) => money(value),
                            },
                        },
                    },
                };
            })()
        JS);
    }
}
