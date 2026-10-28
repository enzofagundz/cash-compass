<?php

namespace App\Filament\Widgets;

use App\Services\SpendingReport;
use Filament\Support\RawJs;

class WeekdaySpendChart extends ReportChartWidget
{
    protected ?string $heading = 'Dias da semana';

    protected ?string $description = 'Gasto total por dia da semana no período.';

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

        $rows = app(SpendingReport::class)->weekdays($user, $this->reportPeriod());

        if (array_sum(array_column($rows, 'total_cents')) === 0) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Gasto por dia da semana',
                    'data' => array_map(fn (array $row): float => (float) $row['total'], $rows),
                    'backgroundColor' => $this->chartColor(),
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
