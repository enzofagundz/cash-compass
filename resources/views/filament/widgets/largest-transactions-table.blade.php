<x-filament-widgets::widget>
    <x-filament::section
        heading="Cinco maiores lançamentos"
        description="Maiores gastos do período."
    >
        @if ($rows === [])
            <x-filament::empty-state
                :contained="false"
                icon-color="gray"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedBanknotes"
                heading="Nenhum lançamento de gasto no período."
                description="Ajuste o período para ver outros lançamentos."
            />
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm text-[var(--cc-ink)]">
                    <thead>
                        <tr class="bg-[var(--cc-yellow)] text-left">
                            <th scope="col" class="border-b-[3px] border-[var(--cc-ink)] px-3 py-2 text-[0.6875rem] font-bold uppercase tracking-[0.08em] whitespace-nowrap">
                                Data
                            </th>
                            <th scope="col" class="border-b-[3px] border-[var(--cc-ink)] px-3 py-2 text-[0.6875rem] font-bold uppercase tracking-[0.08em]">
                                Descrição
                            </th>
                            <th scope="col" class="border-b-[3px] border-[var(--cc-ink)] px-3 py-2 text-[0.6875rem] font-bold uppercase tracking-[0.08em]">
                                Tags
                            </th>
                            <th scope="col" class="border-b-[3px] border-[var(--cc-ink)] px-3 py-2 text-right text-[0.6875rem] font-bold uppercase tracking-[0.08em] whitespace-nowrap">
                                Valor
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y-2 divide-[var(--cc-line)]">
                        @foreach ($rows as $row)
                            <tr
                                wire:key="largest-transaction-{{ $row['id'] }}"
                                class="transition-colors hover:bg-[var(--cc-yellow-soft)]"
                            >
                                <td class="px-3 py-2 text-[var(--cc-ink-muted)] whitespace-nowrap">
                                    {{ $row['date'] }}
                                </td>

                                <td class="px-3 py-2 font-semibold">
                                    {{ $row['description'] }}
                                </td>

                                <td class="px-3 py-2">
                                    @if ($row['tags'] === [])
                                        <span class="text-[var(--cc-ink-muted)]">—</span>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($row['tags'] as $tag)
                                                <x-filament::badge color="gray" size="sm">
                                                    {{ $tag }}
                                                </x-filament::badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                <td class="px-3 py-2 text-right font-bold tabular-nums whitespace-nowrap">
                                    {{ $row['amount'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
