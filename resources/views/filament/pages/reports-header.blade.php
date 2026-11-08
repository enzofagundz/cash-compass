{{--
    Reports header: same yellow highlight strip and red marker as the
    Dashboard, with the resolved period as a paper chip so the filter result is
    readable before the form.
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

    @if (filled($period))
        <p class="inline-flex max-w-full shrink-0 items-center gap-2 self-start border-[3px] border-[var(--cc-ink)] bg-[var(--cc-paper)] px-3 py-1.5 text-sm font-bold text-[var(--cc-ink)] sm:self-auto">
            <x-filament::icon
                :icon="\Filament\Support\Icons\Heroicon::OutlinedCalendarDays"
                class="size-4 shrink-0"
            />

            <span class="min-w-0 break-words">{{ $period }}</span>
        </p>
    @endif
</div>
