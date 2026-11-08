<?php

namespace App\Filament\Widgets;

use App\Services\SpendingReport;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;

class HourlySpendChart extends ReportChartWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Horário do lançamento';

    protected ?string $description = 'Usa o horário de criação dos lançamentos manuais.';

    protected ?string $emptyStateHeading = 'Nenhum lançamento manual no período.';

    protected ?string $emptyStateDescription = 'O gráfico considera apenas lançamentos manuais com gasto.';

    protected string|BackedEnum|null $emptyStateIcon = Heroicon::OutlinedClock;

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

        $rows = app(SpendingReport::class)->hourly($user, $this->reportPeriod());

        if ($rows === []) {
            return [];
        }

        $byHour = collect($rows)->keyBy('hour');
        $hours = range(0, 23);

        return [
            'datasets' => [
                [
                    'label' => 'Gasto por horário',
                    'data' => array_map(
                        fn (int $hour): float => (float) ($byHour[$hour]['total'] ?? 0),
                        $hours,
                    ),
                    'counts' => array_map(
                        fn (int $hour): int => (int) ($byHour[$hour]['count'] ?? 0),
                        $hours,
                    ),
                    'backgroundColor' => $this->chartColor(),
                    'borderRadius' => 0,
                    'borderColor' => $this->chartInk(),
                    'hoverBorderColor' => $this->chartInk(),
                    'maxBarThickness' => 18,
                ],
            ],
            'labels' => array_map(
                fn (int $hour): string => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).'h',
                $hours,
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
                scales: {
                    ...theme.scales,
                    x: {
                        ...theme.scales.x,
                        ticks: {
                            ...theme.scales.x.ticks,
                            autoSkip: true,
                            maxRotation: 0,
                        },
                    },
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
                            label: (context) => {
                                const count = context.dataset.counts?.[context.dataIndex] ?? 0;
                                const total = count === 1 ? '1 lançamento' : `${count} lançamentos`;

                                return `${money(context.raw)} · ${total}`;
                            },
                        },
                    },
                },
            };
            JS);
    }
}
