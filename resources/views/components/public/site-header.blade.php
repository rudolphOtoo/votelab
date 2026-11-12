{{-- Public page header: the organization running the vote (logo or initial, and name). --}}
@props(['organization', 'program' => null])
<header class="border-b border-hairline bg-surface">
    <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3">
        @php $home = $program ? route('public.program', [$organization, $program]) : null; @endphp
        <a @if ($home) href="{{ $home }}" wire:navigate @endif class="flex min-h-11 min-w-0 items-center gap-3">
            @if ($organization->logo_url)
                <img src="{{ $organization->logo_url }}" alt="" width="36" height="36" class="size-9 shrink-0 rounded-[var(--radius-sm)] bg-subtle object-contain">
            @else
                <span class="flex size-9 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-primary font-semibold text-primary-fg" aria-hidden="true">{{ mb_strtoupper(mb_substr($organization->name, 0, 1)) }}</span>
            @endif
            <span class="truncate text-sm font-semibold text-ink">{{ $organization->name }}</span>
        </a>
        @if ($program)
            <span class="ml-auto hidden truncate text-sm text-ink-secondary sm:block">{{ $program->title }}</span>
        @endif
    </div>
</header>
