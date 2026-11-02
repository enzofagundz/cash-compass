{{--
    Reports header: paper strip with the page title and the resolved period as
    a bordered chip, so the filter result is readable before the form.
--}}
<div
    class="flex flex-col gap-4 border-[3px] border-[var(--cc-ink)] bg-[var(--cc-paper)] px-4 py-4 shadow-[5px_5px_0_var(--cc-ink)] sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:px-6 sm:py-5"
>
    <h1 class="min-w-0 truncate text-[clamp(1.75rem,2.2vw,2.25rem)] font-black leading-[1.05] tracking-[-0.03em] text-[var(--cc-ink)]">
        {{ $heading }}
    </h1>

    @if (filled($period))
        <p class="inline-flex shrink-0 items-center gap-2 self-start border-[3px] border-[var(--cc-ink)] bg-[var(--cc-yellow)] px-3 py-1.5 text-sm font-bold text-[var(--cc-ink)] sm:self-auto">
            <x-filament::icon
                :icon="\Filament\Support\Icons\Heroicon::OutlinedCalendarDays"
                class="size-4"
            />

            {{ $period }}
        </p>
    @endif
</div>
