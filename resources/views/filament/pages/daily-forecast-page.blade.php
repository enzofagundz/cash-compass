<x-filament-panels::page>
    @php
        $summary = $this->summary();
    @endphp

    <div class="flex flex-col gap-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-filament::section compact>
                <x-slot name="heading">Previsão diária</x-slot>

                <p class="text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ $this->formatMoney($summary['daily_amount']) }}
                </p>

                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Vale para cada dia a partir de hoje.
                </p>
            </x-filament::section>

            <x-filament::section compact>
                <x-slot name="heading">Total mensal</x-slot>

                <p class="text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ $this->formatMoney($summary['monthly_total']) }}
                </p>

                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $summary['count'] }} {{ $summary['count'] === 1 ? 'item' : 'itens' }} na previsão.
                </p>
            </x-filament::section>

            <x-filament::section compact>
                <x-slot name="heading">Divisor</x-slot>

                <p class="text-2xl font-semibold text-gray-950 dark:text-white">
                    {{ $summary['divisor_days'] }} dias
                </p>

                <x-filament::button
                    class="mt-2"
                    color="gray"
                    size="sm"
                    outlined
                    wire:click="mountAction('configureDivisor')"
                >
                    Alterar divisor
                </x-filament::button>
            </x-filament::section>
        </div>

        <x-filament::section compact>
            <x-slot name="heading">Gastos variáveis do mês</x-slot>

            <x-slot name="afterHeader">
                <x-filament::button
                    size="sm"
                    icon="heroicon-m-plus"
                    wire:click="mountAction('newItem')"
                >
                    Nova previsão
                </x-filament::button>
            </x-slot>

            @if ($summary['items'] === [])
                <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    Nenhuma previsão cadastrada. Adicione os gastos variáveis do mês para calcular a previsão diária.
                </p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($summary['items'] as $item)
                        <li
                            wire:key="daily-forecast-{{ $item['id'] }}"
                            class="flex items-center justify-between gap-3 py-3"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $item['description'] }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $this->formatMoney($item['amount']) }}</p>
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <x-filament::icon-button
                                    icon="heroicon-m-pencil-square"
                                    label="Editar {{ $item['description'] }}"
                                    color="gray"
                                    size="sm"
                                    wire:click="mountAction('editItem', { item: {{ $item['id'] }} })"
                                />

                                <x-filament::icon-button
                                    icon="heroicon-m-trash"
                                    label="Excluir {{ $item['description'] }}"
                                    color="danger"
                                    size="sm"
                                    wire:click="deleteItem({{ $item['id'] }})"
                                />
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
