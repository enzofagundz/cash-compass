@php
    $months = $this->horizon;
    $columns = $this->columns;
    $badges = collect($columns)->mapWithKeys(fn ($type): array => [
        $type->value => rtrim(view('filament.pages.thermometer.type-badge', ['type' => $type, 'filled' => true])->render()),
    ])->all();
    $badgeIcons = collect($columns)->mapWithKeys(fn ($type): array => [
        $type->value => $type->badgeSymbol() !== null
            ? '<svg class="thermometer-badge-icon" focusable="false"><use href="#'.$type->badgeSymbol().'" /></svg>'
            : ($type->badgeLetter() ?? ''),
    ])->all();
@endphp

@if ($months === [])
    <div class="thermometer-empty">
        <p>Não foi possível carregar o período.</p>
    </div>
@else
    <p class="thermometer-legend">
        Saldo acumulado: entradas menos saídas, diários, economias e cartão. Dias futuros mostram a previsão diária até receberem lançamento.
    </p>

    @if ($this->initialBalanceSummary !== null)
        <p class="thermometer-legend">
            Saldo inicial de {{ $this->formatMoney($this->initialBalanceSummary['amount']) }} conta a partir de {{ $this->initialBalanceSummary['starts_at'] }}.
        </p>
    @endif

    <div
        x-data="{
            index: 0,
            atEnd: false,
            updateAtEnd() {
                const el = this.$refs.scroller;

                if (!el) {
                    return;
                }

                // Browsers stop a few pixels short of the trailing padding, so
                // the end is reached slightly before scrollWidth - clientWidth.
                this.atEnd = el.scrollLeft + el.clientWidth >= el.scrollWidth - 32;
            },
            scrollNext() {
                const el = this.$refs.scroller;

                if (el) {
                    el.scrollBy({ left: Math.round(el.clientWidth * 0.8), behavior: 'smooth' });
                }
            },
        }"
        x-init="$nextTick(() => {
            updateAtEnd();

            if ($refs.months) {
                new ResizeObserver(() => updateAtEnd()).observe($refs.months);
            }
        })"
        x-on:resize.window.debounce.150ms="updateAtEnd()"
    >
        @include('filament.pages.thermometer.icons')

        <div class="thermometer-scroll-wrap">
            <div class="thermometer-scroll" x-ref="scroller" x-on:scroll.debounce.100ms="updateAtEnd()">
                <div class="thermometer-months" x-ref="months">
                    @foreach ($months as $offset => $month)
                        <div
                            class="thermometer-month-wrapper"
                            wire:key="thermometer-month-{{ $month['year'] }}-{{ $month['month'] }}"
                            x-bind:class="index === {{ $offset }} ? '' : 'hidden lg:block'"
                        >
                            @include('filament.pages.thermometer.month-grid', [
                                'month' => $month,
                                'columns' => $columns,
                                'badges' => $badges,
                                'badgeIcons' => $badgeIcons,
                            ])
                        </div>
                    @endforeach
                </div>
            </div>

            <button
                type="button"
                class="thermometer-scroll-cue"
                aria-label="Ver mais colunas"
                x-cloak
                x-show="!atEnd"
                x-on:click="scrollNext()"
            >
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" focusable="false">
                    <path fill-rule="evenodd" d="M7.22 4.22a.75.75 0 0 1 1.06 0l5.25 5.25a.75.75 0 0 1 0 1.06l-5.25 5.25a.75.75 0 0 1-1.06-1.06L11.94 10 7.22 5.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                </svg>
            </button>
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

                <span class="thermometer-mobile-nav-label" x-text="'Mês ' + (index + 1) + ' de {{ count($months) }}'"></span>

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
