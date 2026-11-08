{{--
    Dashboard header: the page title lives on a yellow highlight strip with the
    foundation border and hard shadow, and the existing header actions keep
    their usual place on the right.
--}}
<div
    class="flex flex-col gap-4 border-[3px] border-[var(--cc-ink)] bg-[var(--cc-yellow)] px-4 py-4 shadow-[5px_5px_0_var(--cc-ink)] sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:px-6 sm:py-5"
>
    <div class="flex min-w-0 items-center gap-3">
        <span
            aria-hidden="true"
            class="hidden size-3 shrink-0 border-[3px] border-[var(--cc-ink)] bg-[var(--cc-red)] sm:block"
        ></span>

        <h1 class="truncate text-[clamp(1.75rem,2.2vw,2.25rem)] font-black leading-[1.05] tracking-[-0.03em] text-[var(--cc-ink)]">
            {{ $heading }}
        </h1>
    </div>

    @if (filled($actions))
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <x-filament::actions :actions="$actions" />
        </div>
    @endif
</div>
