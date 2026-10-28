<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithSpendingReport;
use App\Filament\Widgets\Concerns\VisibleToNonAdminWidgets;
use App\Services\SpendingReport;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class HourlySpendChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use InteractsWithSpendingReport;
    use VisibleToNonAdminWidgets;

    protected ?string $pollingInterval = null;

    public function updatedPageFilters(): void
    {
        $this->cachedData = null;
    }

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Horário do lançamento';

    protected ?string $description = 'Usa o horário de criação dos lançamentos manuais.';

    protected ?string $emptyStateHeading = 'Nenhum lançamento manual no período.';

    protected ?string $emptyStateDescription = 'O gráfico considera apenas lançamentos manuais com gasto.';

    protected function getType(): string
    {
        return 'bubble';
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

        $points = array_map(
            fn (array $row): array => [
                'x' => $row['hour'],
                'y' => (float) $row['total'],
                'r' => $row['radius'],
                'count' => $row['count'],
                'label' => $row['label'],
            ],
            app(SpendingReport::class)->hourly($user, $this->reportPeriod()),
        );

        if ($points === []) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Gasto por horário',
                    'data' => $points,
                    'backgroundColor' => $this->chartColor(),
                    'borderColor' => $this->chartColor(),
                ],
            ],
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
                    scales: {
                        x: {
                            min: 0,
                            max: 23,
                            ticks: {
                                stepSize: 1,
                                callback: (value) => String(value).padStart(2, '0') + 'h',
                            },
                        },
                        y: {
                            ticks: {
                                callback: (value) => money(value),
                            },
                        },
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            callbacks: {
                                title: (items) => items[0]?.raw?.label ?? '',
                                label: (context) => {
                                    const point = context.raw;
                                    const count = point.count === 1 ? '1 lançamento' : `${point.count} lançamentos`;

                                    return `${money(point.y)} · ${count}`;
                                },
                            },
                        },
                    },
                };
            })()
        JS);
    }
}
