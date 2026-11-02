<?php

namespace App\Filament\Widgets;

use App\Services\SpendingReport;
use Filament\Support\RawJs;

class WeekdaySpendChart extends ReportChartWidget
{
    protected ?string $heading = 'Dias da semana';

    protected ?string $description = 'Gasto total por dia da semana no período.';

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

        $rows = app(SpendingReport::class)->weekdays($user, $this->reportPeriod());

        if (array_sum(array_column($rows, 'total_cents')) === 0) {
            return [];
        }

        $peakCents = array_reduce(
            $rows,
            fn (?int $carry, array $row): int => max($carry ?? 0, (int) $row['total_cents']),
        ) ?? 0;

        return [
            'datasets' => [
                [
                    'label' => 'Gasto por dia da semana',
                    'data' => array_map(fn (array $row): float => (float) $row['total'], $rows),
                    'backgroundColor' => array_map(
                        fn (array $row): string => (int) $row['total_cents'] === (int) $peakCents
                            ? $this->chartColor()
                            : $this->chartNeutralColor(),
                        $rows,
                    ),
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
