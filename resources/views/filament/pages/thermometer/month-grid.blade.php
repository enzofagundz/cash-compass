<table class="thermometer-month">
    <caption class="thermometer-month-caption">{{ $month['label'] }}</caption>

    <thead>
        <tr>
            <th scope="col" class="thermometer-col-day">dia</th>

            @foreach ($columns as $type)
                <th scope="col" class="thermometer-col-type">
                    <span class="inline-flex items-center gap-1">
                        {!! $badges[$type->value] !!}
                        <span>{{ $type->columnLabel() }}</span>
                    </span>
                </th>
            @endforeach

            <th scope="col" class="thermometer-col-balance">saldos</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($month['days'] as $day)
            @php
                $dateKey = $day['date'];
                $isCheckedIn = $day['is_checked_in'];
                $dayLabel = $day['label'];
                $shortDay = $day['short_date'];
                $balance = $this->formatMoney($day['balance']);
                $balanceColor = $day['balance_color'];
                $filledTypes = array_flip($day['filled_types']);
                $projectedTypes = array_flip($day['projected_types']);
                $checkMark = $isCheckedIn
                    ? '<svg class="thermometer-day-check" aria-hidden="true" focusable="false"><use href="#tmb-icon-check" /></svg>'
                    : '';
            @endphp<tr wire:key="thermometer-day-{{ $dateKey }}" @class(['thermometer-row', 'thermometer-row-today' => $day['is_today'], 'thermometer-row-weekend' => $day['is_weekend']])><th scope="row" class="thermometer-day-cell">@if ($day['is_future'])<span class="thermometer-day-static" title="{{ $dayLabel }}">{{ $day['day'] }}</span>@else<button type="button" class="thermometer-day-button" wire:click="toggleCheckIn('{{ $dateKey }}')" aria-pressed="{{ $isCheckedIn ? 'true' : 'false' }}" aria-label="Alternar check-in do dia {{ $dayLabel }}"><span>{{ $day['day'] }}</span>{!! $checkMark !!}</button>@endif</th>@foreach ($columns as $type)@php
    $value = $day[$type->value];
    $isFilled = isset($filledTypes[$type->value]);
    $isProjected = isset($projectedTypes[$type->value]);
    $money = $this->formatMoney($value);
    $badge = $badgeIcons[$type->value];
@endphp<td class="thermometer-value-cell"><div class="thermometer-cell-inner"><button type="button" class="thermometer-value-button" data-filled="{{ $isFilled ? 'true' : 'false' }}" wire:click="mountAction('openCell', { date: '{{ $dateKey }}', type: '{{ $type->value }}' })" aria-label="Ver {{ mb_strtolower($type->columnLabel()) }} de {{ $shortDay }}, total {{ $money }}{{ $isProjected ? ', com projeções' : '' }}"><span class="thermometer-badge" data-type="{{ $type->value }}" data-filled="{{ $isFilled ? 'true' : 'false' }}" aria-hidden="true">{!! $badge !!}</span><span @class(['thermometer-value', 'thermometer-value-projection' => $isProjected])>{{ $money }}</span></button><button type="button" class="thermometer-add-button" wire:click="mountAction('addMovement', { date: '{{ $dateKey }}', type: '{{ $type->value }}' })" aria-label="Adicionar {{ mb_strtolower($type->label()) }} em {{ $shortDay }}"><span aria-hidden="true">+</span></button></div></td>@endforeach<td class="thermometer-balance-cell" data-balance-color="{{ $balanceColor }}"><button type="button" class="thermometer-balance-button" wire:click="mountAction('openCell', { date: '{{ $dateKey }}' })" aria-label="Saldo do dia {{ $dayLabel }}: {{ $balance }}">{{ $balance }}</button></td></tr>
        @endforeach
    </tbody>

    <tfoot>
        <tr>
            <th scope="row">total</th>

            @foreach ($columns as $type)
                <td>
                    <span class="inline-flex items-center justify-end gap-1">
                        {!! $badges[$type->value] !!}
                        <span>{{ $this->formatMoney($month['totals'][$type->value]) }}</span>
                    </span>
                </td>
            @endforeach

            <td class="thermometer-total-balance"></td>
        </tr>
    </tfoot>
</table>
