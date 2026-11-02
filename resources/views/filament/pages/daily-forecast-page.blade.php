<x-filament-panels::page>
    @php
        $summary = $this->summary();
    @endphp

    <div class="flex flex-col gap-4">
        <div class="rounded-[4px] border-[3px] border-[color:var(--cc-ink)] bg-[var(--cc-yellow)] p-4 shadow-[4px_4px_0_var(--cc-ink)] sm:p-6">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-[color:var(--cc-ink-muted)]">Previsão diária</p>

                    <p class="mt-1 text-2xl font-black tabular-nums text-[color:var(--cc-ink)]">
                        {{ $this->formatMoney($summary['daily_amount']) }}
                    </p>

                    <p class="mt-1 text-xs text-[color:var(--cc-ink-muted)]">
                        Vale para cada dia a partir de hoje.
                    </p>
                </div>

                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-[color:var(--cc-ink-muted)]">Total mensal</p>

                    <p class="mt-1 text-2xl font-black tabular-nums text-[color:var(--cc-ink)]">
                        {{ $this->formatMoney($summary['monthly_total']) }}
                    </p>

                    <p class="mt-1 text-xs text-[color:var(--cc-ink-muted)]">
                        {{ $summary['count'] }} {{ $summary['count'] === 1 ? 'item' : 'itens' }} na previsão.
                    </p>
                </div>

                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-[color:var(--cc-ink-muted)]">Divisor</p>

                    <div class="mt-1 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <p class="text-2xl font-black tabular-nums text-[color:var(--cc-ink)]">
                            {{ $summary['divisor_days'] }} dias
                        </p>

                        <x-filament::link
                            size="sm"
                            wire:click="mountAction('configureDivisor')"
                        >
                            Alterar divisor
                        </x-filament::link>
                    </div>

                    <p class="mt-1 text-xs text-[color:var(--cc-ink-muted)]">
                        O total mensal é dividido por este número de dias.
                    </p>
                </div>
            </div>
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
                <div class="rounded-[4px] border-[2px] border-dashed border-[color:var(--cc-line)] px-4 py-8 text-center">
                    <p class="text-sm text-[color:var(--cc-ink-muted)]">
                        Nenhuma previsão cadastrada. Adicione os gastos variáveis do mês para calcular a previsão diária.
                    </p>
                </div>
            @else
                <ul class="flex flex-col gap-2">
                    @foreach ($summary['items'] as $item)
                        <li
                            wire:key="daily-forecast-{{ $item['id'] }}"
                            class="flex items-center gap-3 rounded-[4px] border-[2px] border-[color:var(--cc-ink)] bg-[var(--cc-paper)] px-3 py-2"
                        >
                            <p class="min-w-0 flex-1 text-sm font-semibold break-words text-[color:var(--cc-ink)]">
                                {{ $item['description'] }}
                            </p>

                            <p class="shrink-0 text-right text-sm font-bold tabular-nums whitespace-nowrap text-[color:var(--cc-ink)]">
                                {{ $this->formatMoney($item['amount']) }}
                            </p>

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
