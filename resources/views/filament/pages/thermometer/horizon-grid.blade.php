@php
    $months = $this->horizon;
@endphp

@if ($months === [])
    <div class="thermometer-empty">
        <p>Não foi possível carregar o período.</p>
    </div>
@else
    <p class="thermometer-legend">
        O saldo acumulado soma entradas e subtrai saídas, diários, economias e cartão.
        Lançamentos pendentes entram a partir de hoje e aparecem marcados como projeção.
    </p>

    <div x-data="{ index: 0 }">
        @include('filament.pages.thermometer.icons')

        <div class="thermometer-scroll">
            <div class="thermometer-months">
                @foreach ($months as $offset => $month)
                    <div
                        class="thermometer-month-wrapper"
                        wire:key="thermometer-month-{{ $month['year'] }}-{{ $month['month'] }}"
                        x-bind:class="index === {{ $offset }} ? '' : 'hidden lg:block'"
                    >
                        @include('filament.pages.thermometer.month-grid', [
                            'month' => $month,
                            'isCurrentMonth' => $month['year'] === (int) now()->year
                                && $month['month'] === (int) now()->month,
                        ])
                    </div>
                @endforeach
            </div>
        </div>

        @if (count($months) > 1)
            <div class="mt-3 flex items-center justify-between gap-2 lg:hidden">
                <x-filament::button
                    color="gray"
                    size="sm"
                    outlined
                    x-on:click="index = Math.max(0, index - 1)"
                    x-bind:disabled="index === 0"
                >
                    Anterior
                </x-filament::button>

                <span class="text-xs" x-text="'Mês ' + (index + 1) + ' de {{ count($months) }}'"></span>

                <x-filament::button
                    color="gray"
                    size="sm"
                    outlined
                    x-on:click="index = Math.min({{ count($months) - 1 }}, index + 1)"
                    x-bind:disabled="index === {{ count($months) - 1 }}"
                >
                    Próximo
                </x-filament::button>
            </div>
        @endif
    </div>
@endif
