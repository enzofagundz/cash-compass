<x-filament-panels::page>
    <div class="thermometer">
        @include('filament.pages.thermometer.period-toolbar')

        @unless ($this->hasInitialBalance)
            <div
                role="status"
                class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300"
            >
                Nenhum saldo inicial cadastrado: o termômetro parte de R$ 0,00 e continua aceitando lançamentos.
            </div>
        @endunless

        @include('filament.pages.thermometer.horizon-grid')

        @include('filament.pages.thermometer.forecast-summary')
    </div>
</x-filament-panels::page>
