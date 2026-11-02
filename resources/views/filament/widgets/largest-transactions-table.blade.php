<x-filament-widgets::widget>
    <x-filament::section
        heading="Cinco maiores lançamentos"
        description="Maiores gastos do período."
    >
        @if ($rows === [])
            <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Nenhum lançamento de gasto no período.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs font-medium text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th scope="col" class="py-2 pr-3 whitespace-nowrap">Data</th>
                            <th scope="col" class="py-2 pr-3">Descrição</th>
                            <th scope="col" class="py-2 pr-3">Tags</th>
                            <th scope="col" class="py-2 pl-3 text-right whitespace-nowrap">Valor</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($rows as $row)
                            <tr wire:key="largest-transaction-{{ $row['id'] }}">
                                <td class="py-2 pr-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $row['date'] }}
                                </td>

                                <td class="py-2 pr-3 font-medium text-gray-950 dark:text-white">
                                    {{ $row['description'] }}
                                </td>

                                <td class="py-2 pr-3">
                                    @if ($row['tags'] === [])
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
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

                                <td class="py-2 pl-3 text-right font-medium text-gray-950 dark:text-white whitespace-nowrap">
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
