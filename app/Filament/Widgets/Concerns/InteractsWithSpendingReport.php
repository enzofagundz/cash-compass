<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\TagColor;
use App\Models\User;
use App\Services\SpendingReport;
use Carbon\CarbonImmutable;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;

/**
 * Shared plumbing between the report widgets: period resolution, money
 * formatting and chart palette lookups. The financial rules stay in
 * SpendingReport.
 */
trait InteractsWithSpendingReport
{
    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, previous_start: CarbonImmutable, previous_end: CarbonImmutable, preset: string, label: string, is_custom: bool}
     */
    protected function reportPeriod(): array
    {
        return app(SpendingReport::class)->period($this->pageFilters);
    }

    protected function reportUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    protected function formatMoney(string $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    protected function formatSignedMoney(int $cents): string
    {
        $sign = $cents > 0 ? '+' : ($cents < 0 ? '-' : '');

        return $sign.'R$ '.number_format(abs($cents) / 100, 2, ',', '.');
    }

    protected function formatPercentage(float $value): string
    {
        return number_format($value, 1, ',', '.').'%';
    }

    /**
     * Primary chart fill: the foundation red (`--cc-red`). Chart.js paints on a
     * canvas, so the palette is mirrored here instead of read from the theme.
     */
    protected function chartColor(): string
    {
        return '#ff5252';
    }

    /**
     * Accent fill for the point of the chart (peak, largest bar): `--cc-yellow`.
     */
    protected function chartEmphasisColor(): string
    {
        return '#ffdd55';
    }

    /**
     * Warm neutral used for the data that is not the point of the chart.
     */
    protected function chartNeutralColor(): string
    {
        return '#dfd3b6';
    }

    /**
     * Ink used for the bar outline, so every bar reads as a block.
     */
    protected function chartInk(): string
    {
        return '#16130f';
    }

    protected function tagColorHex(?TagColor $color): string
    {
        $palette = FilamentColor::getColor($color?->getColor() ?? 'gray') ?? Color::Gray;

        return Color::convertToHex((string) ($palette[500] ?? $palette[400]));
    }
}
