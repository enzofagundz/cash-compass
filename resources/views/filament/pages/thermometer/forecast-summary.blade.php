@php
    $summary = $this->forecastSummary();
@endphp

<section class="thermometer-forecast" aria-label="Previsão de diário">
    <div>
        <p class="thermometer-forecast-title">Previsão de diário</p>
        <p class="thermometer-forecast-value">{{ $this->formatMoney($summary['daily_amount']) }}</p>
        <p class="thermometer-legend">
            Este valor é uma previsão dos gastos variáveis do mês:
            {{ $this->formatMoney($summary['monthly_total']) }} ÷ {{ $summary['divisor_days'] }} dias.
        </p>
    </div>

    <x-filament::button
        color="gray"
        size="sm"
        outlined
        icon="heroicon-o-pencil-square"
        tag="a"
        :href="$summary['edit_url']"
    >
        Editar previsão
    </x-filament::button>
</section>
