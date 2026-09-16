<div class="thermometer-toolbar" role="toolbar" aria-label="Período dos saldos">
    <x-filament::icon-button
        icon="heroicon-o-chevron-double-left"
        label="Ano anterior"
        color="gray"
        size="sm"
        wire:click="previousYear"
    />

    <x-filament::icon-button
        icon="heroicon-o-chevron-left"
        label="Mês anterior"
        color="gray"
        size="sm"
        wire:click="previousMonth"
    />

    <x-filament::button
        color="gray"
        size="sm"
        outlined
        icon="heroicon-o-calendar-days"
        wire:click="mountAction('selectPeriod')"
        aria-label="Selecionar período. Período {{ $this->periodLabel }}"
    >
        {{ $this->periodLabel }}
    </x-filament::button>

    <x-filament::icon-button
        icon="heroicon-o-chevron-right"
        label="Mês seguinte"
        color="gray"
        size="sm"
        wire:click="nextMonth"
    />

    <x-filament::icon-button
        icon="heroicon-o-chevron-double-right"
        label="Ano seguinte"
        color="gray"
        size="sm"
        wire:click="nextYear"
    />

    <x-filament::button
        color="gray"
        size="sm"
        outlined
        icon="heroicon-o-calendar"
        wire:click="goToToday"
    >
        Hoje
    </x-filament::button>

    <x-filament::button
        color="gray"
        size="sm"
        outlined
        icon="heroicon-o-arrows-right-left"
        wire:click="mountAction('configureHorizon')"
    >
        Horizonte: {{ $this->horizonMonths }}
    </x-filament::button>

    <span class="thermometer-toolbar-spacer" aria-hidden="true"></span>

    <span wire:loading.delay class="thermometer-legend" role="status">Atualizando…</span>
</div>
