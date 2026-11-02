<x-filament-widgets::widget>
    <x-filament::section
        heading="Todas as tags"
        description="Um lançamento entra inteiro em cada tag, então a soma das tags pode passar do total gasto."
    >
        @if ($total === 0)
            <x-filament::empty-state
                :contained="false"
                icon-color="gray"
                :icon="\Filament\Support\Icons\Heroicon::OutlinedTag"
                heading="Nenhuma tag com gasto no período."
                description="Vincule tags aos lançamentos de gasto para ver a participação."
            />
        @else
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm text-[var(--cc-ink)]">
                    <thead>
                        <tr class="bg-[var(--cc-yellow)] text-left">
                            <th scope="col" class="border-b-[3px] border-[var(--cc-ink)] px-3 py-2 text-[0.6875rem] font-bold uppercase tracking-[0.08em]">
                                Tag
                            </th>
                            <th scope="col" class="border-b-[3px] border-[var(--cc-ink)] px-3 py-2 text-right text-[0.6875rem] font-bold uppercase tracking-[0.08em] whitespace-nowrap">
                                Total
                            </th>
                            <th scope="col" class="border-b-[3px] border-[var(--cc-ink)] px-3 py-2 text-right text-[0.6875rem] font-bold uppercase tracking-[0.08em] whitespace-nowrap">
                                Participação
                            </th>
                            <th scope="col" class="border-b-[3px] border-[var(--cc-ink)] px-3 py-2 text-right text-[0.6875rem] font-bold uppercase tracking-[0.08em] whitespace-nowrap">
                                Lançamentos
                            </th>
                            <th scope="col" class="border-b-[3px] border-[var(--cc-ink)] px-3 py-2 text-right text-[0.6875rem] font-bold uppercase tracking-[0.08em] whitespace-nowrap">
                                Média
                            </th>
                            <th scope="col" class="border-b-[3px] border-[var(--cc-ink)] px-3 py-2 text-right text-[0.6875rem] font-bold uppercase tracking-[0.08em] whitespace-nowrap">
                                Última data
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y-2 divide-[var(--cc-line)]">
                        @foreach ($rows as $row)
                            <tr
                                wire:key="report-tag-{{ $row['id'] ?? 'none' }}"
                                class="transition-colors hover:bg-[var(--cc-yellow-soft)]"
                            >
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            aria-hidden="true"
                                            class="inline-block size-3 shrink-0 rounded-full border-2 border-[var(--cc-ink)]"
                                            style="background-color: {{ $row['dot'] }}"
                                        ></span>

                                        <span class="font-semibold">
                                            {{ $row['name'] }}
                                        </span>

                                        @unless ($row['is_active'])
                                            <x-filament::badge color="gray" size="sm">
                                                (arquivada)
                                            </x-filament::badge>
                                        @endunless
                                    </div>
                                </td>

                                <td class="px-3 py-2 text-right font-bold tabular-nums whitespace-nowrap">
                                    {{ $row['total'] }}
                                </td>

                                <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                                    {{ $row['percentage'] }}
                                </td>

                                <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                                    {{ $row['count'] }}
                                </td>

                                <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                                    {{ $row['average'] }}
                                </td>

                                <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap">
                                    {{ $row['last_date'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($rows->hasPages())
                <div class="mt-4">
                    <x-filament::pagination :paginator="$rows" />
                </div>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
