@php
    $filled = $filled ?? true;

    $badgeLetter = $type->badgeLetter();
    $badgeSymbol = $type->badgeSymbol();
@endphp

<span
    class="thermometer-badge"
    data-type="{{ $type->value }}"
    data-filled="{{ $filled ? 'true' : 'false' }}"
    aria-hidden="true"
>
    @if ($badgeSymbol !== null)
        <svg class="thermometer-badge-icon" focusable="false"><use href="#{{ $badgeSymbol }}" /></svg>
    @else
        {{ $badgeLetter }}
    @endif
</span>
