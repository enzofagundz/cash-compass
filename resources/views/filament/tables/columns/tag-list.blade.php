<div class="flex flex-wrap items-center gap-1">
    @foreach ($getRecord()->tags->sortBy('normalized_name')->take(2) as $tag)
        <x-tag-badge :tag="$tag" />
    @endforeach

    @if (($tagCount = $getRecord()->tags->count()) > 2)
        <x-filament::badge color="gray" size="sm">+{{ $tagCount - 2 }}</x-filament::badge>
    @endif
</div>
