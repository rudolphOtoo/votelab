{{-- "Find your nominee": a plain GET form to /?q= (works without JavaScript). A code that only one program has jumps
     straight to the nominee; anything else lists the matches. Phones get the button under the field, and the 14 px
     placeholder fits in full at 360 px; typed text stays 16 px so iOS doesn't zoom. --}}
@props(['query' => '', 'row' => 'sm:flex-row'])
<form method="get" action="{{ route('home') }}#search-results" role="search" {{ $attributes }}>
    <div class="flex items-baseline justify-between gap-3">
        <label for="find-nominee" class="text-sm font-semibold">Find your nominee</label>
        <a href="#live" class="inline-flex min-h-11 items-center gap-1 text-sm font-medium underline-offset-4 hover:underline">
            Vote now <x-ui.icon name="chevron-down" class="size-4" />
        </a>
    </div>
    <div class="mt-1 flex flex-col gap-2 {{ $row }}">
        <input id="find-nominee" name="q" type="search" value="{{ $query }}" autocomplete="off" enterkeyhint="search" maxlength="80"
            placeholder="Enter a nominee code, e.g. AM27, or a name"
            class="min-h-13 w-full rounded-[var(--radius-md)] border border-hero-field-border bg-hero-field px-3 text-base text-hero-ink placeholder:text-sm placeholder:text-hero-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hero-focus">
        <button type="submit" class="inline-flex min-h-12 shrink-0 items-center justify-center gap-2 rounded-[var(--radius-md)] bg-hero-accent px-5 font-semibold text-hero-accent-fg transition-[filter] duration-[var(--duration-fast)] hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hero-focus">
            <x-ui.icon name="search" class="size-5" /> Find
        </button>
    </div>
</form>
