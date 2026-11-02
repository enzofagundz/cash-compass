<x-filament-panels::page>
    <div class="thermometer">
        @include('filament.pages.thermometer.period-toolbar')

        @unless ($this->hasInitialBalance)
            <div role="status" class="thermometer-alert">
                Nenhum saldo inicial cadastrado: o termômetro parte de R$ 0,00 e continua aceitando lançamentos.
            </div>
        @endunless

        @include('filament.pages.thermometer.horizon-grid')

        @include('filament.pages.thermometer.forecast-summary')
    </div>
</x-filament-panels::page>
