{{-- Find your nominee: a plain form (works without JavaScript). On phones the button sits under the field and the
     placeholder is 14 px, so its full text fits at 360 px; typed text stays 16 px so iOS doesn't zoom. --}}
<form method="get" action="{{ request()->url() }}" role="search" class="{{ $class ?? '' }}">
    <div class="flex items-baseline justify-between gap-3">
        <label for="find-nominee" class="text-sm font-semibold">Find your nominee</label>
        <a href="#live" class="inline-flex min-h-11 items-center gap-1 text-sm font-medium underline-offset-4 hover:underline">
            Vote now <x-ui.icon name="chevron-down" class="size-4" />
        </a>
    </div>
    <div class="mt-1 flex flex-col gap-2 {{ $row ?? 'sm:flex-row' }}">
        <input id="find-nominee" name="q" type="search" autocomplete="off" enterkeyhint="search"
            placeholder="Enter a nominee code, e.g. AM27, or a name"
            class="min-h-13 w-full rounded-[var(--search-radius,var(--radius-md))] border border-[var(--hero-field-border)] bg-[var(--hero-field)] px-3 text-base text-[var(--hero-text)] placeholder:text-sm placeholder:text-[var(--hero-muted)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--hero-focus)]">
        <button type="submit" class="inline-flex min-h-12 shrink-0 items-center justify-center gap-2 rounded-[var(--search-radius,var(--radius-md))] bg-[var(--hero-accent)] px-5 font-semibold text-[var(--hero-accent-fg)] transition-[filter] duration-[var(--duration-fast)] hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--hero-focus)]">
            <x-ui.icon name="search" class="size-5" /> Find
        </button>
    </div>
</form>
