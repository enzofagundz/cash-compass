<x-filament-panels::page>
    <div class="thermometer">
        @include('filament.pages.thermometer.period-toolbar')

        @unless ($this->hasInitialBalance)
            <div
                role="status"
                class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200"
            >
                Nenhum saldo inicial cadastrado: o termômetro parte de R$ 0,00 e continua aceitando lançamentos.
            </div>
        @endunless

        @include('filament.pages.thermometer.horizon-grid')
    </div>
</x-filament-panels::page>
