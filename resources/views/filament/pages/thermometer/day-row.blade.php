@php
    $dateKey = $day['date'];
    $date = Carbon\CarbonImmutable::parse($dateKey);
    $isCheckedIn = isset($this->checkIns[$dateKey]);
    $dayLabel = $this->longDayLabel($dateKey);
    $shortDay = $date->format('d/m/Y');
    $balance = $this->formatMoney($day['balance']);
    $checkMark = $isCheckedIn
        ? '<svg class="thermometer-day-check" aria-hidden="true" focusable="false"><use href="#tmb-icon-check" /></svg>'
        : '';
@endphp
<tr wire:key="thermometer-day-{{ $dateKey }}" @class(['thermometer-row', 'thermometer-row-today' => $day['is_today'], 'thermometer-row-weekend' => $date->isWeekend()])><th scope="row" class="thermometer-day-cell">@if ($day['is_future'])<span class="thermometer-day-static" title="{{ $dayLabel }}">{{ $day['day'] }}</span>@else<button type="button" class="thermometer-day-button" wire:click="toggleCheckIn('{{ $dateKey }}')" aria-pressed="{{ $isCheckedIn ? 'true' : 'false' }}" aria-label="Alternar check-in do dia {{ $dayLabel }}"><span>{{ $day['day'] }}</span>{!! $checkMark !!}</button>@endif</th>@foreach ($this->columns as $type)@php
    $value = $day[$type->value];
    $isFilled = (float) $value !== 0.0;
    $isPending = in_array($type->value, $day['pending_types'], true);
    $money = $this->formatMoney($value);
    $badgeSymbol = match ($type) {
        App\Enums\TransactionType::Income => 'tmb-icon-arrow-in',
        App\Enums\TransactionType::Expense => 'tmb-icon-arrow-out',
        default => null,
    };
    $badgeLetter = match ($type) {
        App\Enums\TransactionType::Daily => 'D',
        App\Enums\TransactionType::Savings => 'E',
        App\Enums\TransactionType::Card => 'C',
        default => '',
    };
    $badge = $badgeSymbol !== null
        ? '<svg class="thermometer-badge-icon" focusable="false"><use href="#'.$badgeSymbol.'" /></svg>'
        : $badgeLetter;
    $projection = $isPending
        ? '<span class="thermometer-projection" title="Inclui lançamentos pendentes">proj.</span>'
        : '';
@endphp<td class="thermometer-value-cell"><div class="thermometer-cell-inner"><button type="button" class="thermometer-value-button" data-filled="{{ $isFilled ? 'true' : 'false' }}" wire:click="mountAction('openCell', { date: '{{ $dateKey }}', type: '{{ $type->value }}' })" aria-label="Ver {{ mb_strtolower($type->columnLabel()) }} de {{ $shortDay }}, total {{ $money }}{{ $isPending ? ', com projeções' : '' }}"><span class="thermometer-badge" data-type="{{ $type->value }}" data-filled="{{ $isFilled ? 'true' : 'false' }}" aria-hidden="true">{!! $badge !!}</span><span class="thermometer-value">{{ $money }}</span>{!! $projection !!}</button><button type="button" class="thermometer-add-button" wire:click="mountAction('addMovement', { date: '{{ $dateKey }}', type: '{{ $type->value }}' })" aria-label="Adicionar {{ mb_strtolower($type->label()) }} em {{ $shortDay }}"><span aria-hidden="true">+</span></button></div></td>@endforeach<td @class(['thermometer-balance-cell' => $isCurrentMonth])><button type="button" @class(['thermometer-balance-button', 'thermometer-balance-negative' => str_starts_with($day['balance'], '-')]) wire:click="mountAction('openCell', { date: '{{ $dateKey }}' })" aria-label="Saldo do dia {{ $dayLabel }}: {{ $balance }}">{{ $balance }}</button></td></tr>
