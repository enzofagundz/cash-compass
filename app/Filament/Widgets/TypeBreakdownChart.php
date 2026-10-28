<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Services\SpendingReport;
use Filament\Support\Colors\Color;
use Filament\Support\RawJs;

class TypeBreakdownChart extends ReportChartWidget
{
    protected ?string $heading = 'Tipos de gasto';

    protected ?string $description = 'Participação de Saída, Diário e Cartão no total do período.';

    protected function getType(): string
    {
        return 'doughnut';
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

        $rows = app(SpendingReport::class)->types($user, $this->reportPeriod());

        if (array_sum(array_column($rows, 'total_cents')) === 0) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Gasto por tipo',
                    'data' => array_map(fn (array $row): float => (float) $row['total'], $rows),
                    'backgroundColor' => array_map(fn (array $row): string => $this->typeColor($row['type']), $rows),
                    'borderColor' => array_map(fn (array $row): string => $this->typeColor($row['type']), $rows),
                ],
            ],
            'labels' => array_map(
                fn (array $row): string => $row['label'].': '.$this->formatMoney($row['total'])
                    .' ('.$this->formatPercentage($row['percentage']).')',
                $rows,
            ),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => context.label,
                        },
                    },
                },
            }
        JS);
    }

    private function typeColor(string $type): string
    {
        $palette = match ($type) {
            TransactionType::Expense->value => Color::Red,
            TransactionType::Daily->value => Color::Amber,
            default => Color::Blue,
        };

        return Color::convertToHex($palette[500]);
    }
}
