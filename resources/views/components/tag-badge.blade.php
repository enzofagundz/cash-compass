@props([
    'tag',
])

<x-filament::badge :color="$tag->color->getColor()" size="sm">
    {{ $tag->name }}

    @unless ($tag->is_active)
        <span class="ml-1">(arquivada)</span>
    @endunless
</x-filament::badge>
