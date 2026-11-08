<?php

namespace App\Filament\Widgets;

use App\Services\SpendingReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;

class WeekdaySpendChart extends ReportChartWidget
{
    protected ?string $heading = 'Dias da semana';

    protected ?string $description = 'Gasto total por dia da semana no período.';

    protected ?string $emptyStateHeading = 'Nenhum gasto no período.';

    protected ?string $emptyStateDescription = 'O gráfico soma os gastos realizados de cada dia da semana.';

    protected string|BackedEnum|null $emptyStateIcon = Heroicon::OutlinedCalendarDays;

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
                            ? $this->chartEmphasisColor()
                            : $this->chartColor(),
                        $rows,
                    ),
                    'borderRadius' => 0,
                    'borderColor' => $this->chartInk(),
                    'hoverBorderColor' => $this->chartInk(),
                    'maxBarThickness' => 32,
                ],
            ],
            'labels' => array_map(fn (array $row): string => $row['label'], $rows),
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
                scales: {
                    ...theme.scales,
                    y: {
                        ...theme.scales.y,
                        beginAtZero: true,
                        ticks: {
                            ...theme.scales.y.ticks,
                            maxTicksLimit: 5,
                            callback: (value) => axisMoney(value),
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
