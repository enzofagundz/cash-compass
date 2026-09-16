@php
    $addType = $this->detailType ?: App\Enums\TransactionType::Expense->value;
@endphp

<div class="thermometer-detail">
    <div class="flex items-center gap-2">
        <x-filament::icon-button
            icon="heroicon-o-chevron-left"
            label="Dia anterior"
            color="gray"
            size="sm"
            wire:click="detailPreviousDay"
        />

        <span class="thermometer-detail-header">{{ $this->detailLabel() }}</span>

        <x-filament::icon-button
            icon="heroicon-o-chevron-right"
            label="Dia seguinte"
            color="gray"
            size="sm"
            wire:click="detailNextDay"
        />
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <label class="min-w-40 flex-1">
            <span class="sr-only">Filtrar por tipo</span>

            <select
                wire:model.live="detailType"
                class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
            >
                <option value="">Todos os tipos</option>

                @foreach ($this->columns as $type)
                    <option value="{{ $type->value }}">{{ $type->columnLabel() }}</option>
                @endforeach
            </select>
        </label>

        <x-filament::button
            size="sm"
            icon="heroicon-m-plus"
            wire:click="mountAction('addMovement', { date: '{{ $this->detailDate }}', type: '{{ $addType }}' })"
        >
            Adicionar
        </x-filament::button>
    </div>

    <div class="thermometer-detail-list">
        @forelse ($this->detailRows as $movement)
            <div class="thermometer-movement" wire:key="thermometer-movement-{{ $movement['id'] }}">
                <div class="thermometer-movement-body">
                    <span class="thermometer-movement-title">{{ $movement['description'] }}</span>

                    <span class="thermometer-movement-meta">
                        {{ $movement['date'] }} · {{ $movement['type'] }} · {{ $movement['status'] }}
                        @if ($movement['tags'] !== '')
                            · {{ $movement['tags'] }}
                        @endif
                        @if ($movement['is_recurring'])
                            · recorrente
                        @endif
                    </span>

                    @unless ($movement['counts'])
                        <span class="thermometer-movement-excluded">Fora do cálculo desta célula.</span>
                    @endunless
                </div>

                <span class="thermometer-movement-title">{{ $movement['amount'] }}</span>

                <div class="thermometer-movement-actions">
                    <x-filament::icon-button
                        icon="heroicon-m-pencil-square"
                        label="Editar lançamento"
                        color="gray"
                        size="sm"
                        wire:click="mountAction('editMovement', { movement: {{ $movement['id'] }} })"
                    />

                    @if ($movement['status_value'] === App\Enums\TransactionStatus::Pending->value)
                        <x-filament::icon-button
                            icon="heroicon-m-check-circle"
                            label="Confirmar lançamento"
                            color="success"
                            size="sm"
                            wire:click="mountAction('confirmMovement', { movement: {{ $movement['id'] }} })"
                        />
                    @endif

                    @if ($movement['is_recurring'] && $movement['status_value'] !== App\Enums\TransactionStatus::Skipped->value)
                        <x-filament::icon-button
                            icon="heroicon-m-forward"
                            label="Pular ocorrência"
                            color="gray"
                            size="sm"
                            wire:click="mountAction('skipMovement', { movement: {{ $movement['id'] }} })"
                        />
                    @endif

                    @unless ($movement['is_recurring'])
                        <x-filament::icon-button
                            icon="heroicon-m-trash"
                            label="Excluir lançamento"
                            color="danger"
                            size="sm"
                            wire:click="mountAction('deleteMovement', { movement: {{ $movement['id'] }} })"
                        />
                    @endunless
                </div>
            </div>
        @empty
            <p class="thermometer-empty">
                Sem movimentações por aqui. Use “Adicionar” para criar uma.
            </p>
        @endforelse
    </div>
</div>
