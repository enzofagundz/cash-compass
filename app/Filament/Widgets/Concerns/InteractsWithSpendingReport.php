<?php

namespace App\Filament\Widgets\Concerns;

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

    protected function chartColor(string $name = 'primary', int $shade = 500): string
    {
        $palette = FilamentColor::getColor($name) ?? Color::Amber;

        return Color::convertToHex((string) ($palette[$shade] ?? $palette[500]));
    }
}
