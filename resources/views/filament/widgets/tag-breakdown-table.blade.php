<x-filament-widgets::widget>
    <x-filament::section
        heading="Todas as tags"
        description="A soma das tags pode passar do total gasto, pois um lançamento entra integralmente em cada tag vinculada."
    >
        @if ($total === 0)
            <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Nenhuma tag com gasto no período.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th scope="col" class="py-2 pr-3">Tag</th>
                            <th scope="col" class="py-2 px-3 text-right whitespace-nowrap">Total</th>
                            <th scope="col" class="py-2 px-3 text-right whitespace-nowrap">Participação</th>
                            <th scope="col" class="py-2 px-3 text-right whitespace-nowrap">Lançamentos</th>
                            <th scope="col" class="py-2 px-3 text-right whitespace-nowrap">Média</th>
                            <th scope="col" class="py-2 pl-3 text-right whitespace-nowrap">Última data</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($rows as $row)
                            <tr wire:key="report-tag-{{ $row['id'] ?? 'none' }}">
                                <td class="py-2 pr-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            aria-hidden="true"
                                            class="inline-block size-2.5 shrink-0 rounded-full"
                                            style="background-color: {{ $row['dot'] }}"
                                        ></span>

                                        <span class="font-medium text-gray-950 dark:text-white">
                                            {{ $row['name'] }}
                                        </span>

                                        @unless ($row['is_active'])
                                            <x-filament::badge color="gray" size="sm">
                                                (arquivada)
                                            </x-filament::badge>
                                        @endunless
                                    </div>
                                </td>

                                <td class="py-2 px-3 text-right text-gray-950 dark:text-white whitespace-nowrap">
                                    {{ $row['total'] }}
                                </td>

                                <td class="py-2 px-3 text-right text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $row['percentage'] }}
                                </td>

                                <td class="py-2 px-3 text-right text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $row['count'] }}
                                </td>

                                <td class="py-2 px-3 text-right text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $row['average'] }}
                                </td>

                                <td class="py-2 pl-3 text-right text-gray-500 dark:text-gray-400 whitespace-nowrap">
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
