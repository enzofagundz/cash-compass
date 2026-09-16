@php
    $filled = $filled ?? true;

    $badgeLetter = match ($type) {
        App\Enums\TransactionType::Daily => 'D',
        App\Enums\TransactionType::Savings => 'E',
        App\Enums\TransactionType::Card => 'C',
        default => null,
    };

    $badgeSymbol = match ($type) {
        App\Enums\TransactionType::Income => 'tmb-icon-arrow-in',
        App\Enums\TransactionType::Expense => 'tmb-icon-arrow-out',
        default => null,
    };
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
