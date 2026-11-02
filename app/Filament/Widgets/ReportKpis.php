<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithSpendingReport;
use App\Filament\Widgets\Concerns\VisibleToNonAdminWidgets;
use App\Services\SpendingReport;
use Filament\Support\Icons\Heroicon;
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

    /**
     * Four KPIs stay on one row only when there is room; below that they pair
     * up, so no card is left alone on a row.
     *
     * @var array<string, int>
     */
    protected int|array|null $columns = [
        '@md' => 2,
        '@4xl' => 4,
    ];

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
                ->description($period['label'])
                ->icon(Heroicon::OutlinedBanknotes)
                ->extraAttributes($this->blockAttributes('total', '--cc-red')),
            Stat::make('Variação', $this->variationValue($summary['variation_percent']))
                ->description($this->variationDescription($summary['variation_cents'], $summary['variation_percent']))
                ->color($this->variationColor($summary['variation_cents'], $summary['variation_percent']))
                ->icon($this->variationIcon($summary['variation_cents'], $summary['variation_percent']))
                ->extraAttributes($this->variationAttributes($summary['variation_cents'], $summary['variation_percent'])),
            Stat::make(
                'Média por dia ativo',
                $summary['average_per_active_day'] === null
                    ? '—'
                    : $this->formatMoney($summary['average_per_active_day']),
            )
                ->description($summary['active_days'].' '.($summary['active_days'] === 1 ? 'dia ativo' : 'dias ativos'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->extraAttributes($summary['average_per_active_day'] === null
                    ? ['data-kpi' => 'average']
                    : $this->blockAttributes('average', '--cc-blue')),
            Stat::make('Tag líder', $summary['top_tag']['name'] ?? '—')
                ->description($summary['top_tag'] === null ? '—' : $this->formatMoney($summary['top_tag']['total']))
                ->icon(Heroicon::OutlinedTag)
                ->extraAttributes($summary['top_tag'] === null
                    ? ['data-kpi' => 'top-tag']
                    : $this->blockAttributes('top-tag', '--cc-yellow')),
        ];
    }

    /**
     * Flat accent fill for a KPI card. The theme paints labels, icons and
     * descriptions with the muted ink, so the block re-points that token to
     * the full ink: every text pair on an accent stays readable.
     *
     * @return array<string, string>
     */
    private function blockAttributes(string $key, string $colorVariable): array
    {
        return [
            'data-kpi' => $key,
            'style' => sprintf('background-color: var(%s); --cc-ink-muted: var(--cc-ink)', $colorVariable),
        ];
    }

    /**
     * Variation keeps the paper card when there is nothing to compare, and
     * turns green for a drop and red for a rise in spending.
     *
     * @return array<string, string>
     */
    private function variationAttributes(int $variationCents, ?float $variationPercent): array
    {
        if ($variationPercent === null || $variationCents === 0) {
            return ['data-kpi' => 'variation'];
        }

        return $this->blockAttributes('variation', $variationCents > 0 ? '--cc-red' : '--cc-green');
    }

    private function variationIcon(int $variationCents, ?float $variationPercent): Heroicon
    {
        if ($variationPercent === null || $variationCents === 0) {
            return Heroicon::OutlinedMinus;
        }

        return $variationCents > 0
            ? Heroicon::OutlinedArrowTrendingUp
            : Heroicon::OutlinedArrowTrendingDown;
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
