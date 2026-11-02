<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithSpendingReport;
use App\Filament\Widgets\Concerns\VisibleToNonAdminWidgets;
use App\Services\SpendingReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReportKpis extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use InteractsWithSpendingReport;
    use VisibleToNonAdminWidgets;

    protected ?string $pollingInterval = null;

    public function updatedPageFilters(): void
    {
        $this->cachedStats = null;
    }

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return $this->pageFilters === null
            ? 'Resumo do mês atual'
            : 'Indicadores do período';
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $user = $this->reportUser();

        if ($user === null) {
            return [];
        }

        $report = app(SpendingReport::class);
        $period = $report->period($this->pageFilters);
        $summary = $report->summary($user, $period);

        return [
            Stat::make('Total gasto', $this->formatMoney($summary['total']))
                ->description($period['label']),
            Stat::make('Variação', $this->variationValue($summary['variation_percent']))
                ->description($this->variationDescription($summary['variation_cents'], $summary['variation_percent']))
                ->color($this->variationColor($summary['variation_cents'], $summary['variation_percent'])),
            Stat::make(
                'Média por dia ativo',
                $summary['average_per_active_day'] === null
                    ? '—'
                    : $this->formatMoney($summary['average_per_active_day']),
            )
                ->description($summary['active_days'].' '.($summary['active_days'] === 1 ? 'dia ativo' : 'dias ativos')),
            Stat::make('Tag líder', $summary['top_tag']['name'] ?? '—')
                ->description($summary['top_tag'] === null ? '—' : $this->formatMoney($summary['top_tag']['total'])),
        ];
    }

    private function variationDescription(int $variationCents, ?float $variationPercent): string
    {
        if ($variationPercent === null) {
            return 'Nenhum gasto no período anterior para comparar.';
        }

        if ($variationCents === 0) {
            return 'Mesmo valor do período anterior.';
        }

        return $this->formatSignedMoney($variationCents).' em relação ao período anterior';
    }

    private function variationValue(?float $percent): string
    {
        if ($percent === null) {
            return '—';
        }

        $sign = $percent > 0 ? '+' : ($percent < 0 ? '-' : '');

        return $sign.number_format(abs($percent), 1, ',', '.').'%';
    }

    private function variationColor(int $variationCents, ?float $variationPercent): string
    {
        if ($variationPercent === null || $variationCents === 0) {
            return 'gray';
        }

        return $variationCents > 0 ? 'danger' : 'success';
    }
}
