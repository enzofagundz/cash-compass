@props([
    'tag',
])

<x-filament::badge :color="$tag->color->getColor()" size="sm">
    {{ $tag->name }}
</x-filament::badge>

@unless ($tag->is_active)
    <span class="ml-1 text-xs text-gray-500 dark:text-gray-400">arquivada</span>
@endunless
