{{--
  The organization an organizer is working in. With several, a disclosure lists them as links to
  /dashboard?organization=id (Dashboard switches after checking membership, CurrentOrganization).
--}}
@props(['current', 'organizations'])
@if ($current)
    <div {{ $attributes }}>
        @if ($organizations->count() > 1)
            <details class="group rounded-[var(--radius-md)] border border-hairline">
                <summary class="flex min-h-12 cursor-pointer list-none items-center gap-3 rounded-[var(--radius-md)] px-3 hover:bg-hover [&::-webkit-details-marker]:hidden">
                    <x-public.org-mark :organization="$current" />
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs text-ink-secondary">Organization</span>
                        <span class="block truncate text-sm font-semibold text-ink">{{ $current->name }}</span>
                    </span>
                    <x-ui.icon name="chevron-down" class="size-4 text-ink-secondary transition-transform group-open:rotate-180" />
                </summary>
                <ul class="border-t border-hairline p-1">
                    @foreach ($organizations as $organization)
                        <li>
                            <a href="{{ route('dashboard', ['organization' => $organization->id]) }}" @if ($organization->id === $current->id) aria-current="true" @endif
                               class="flex min-h-11 items-center gap-2 rounded-[var(--radius-sm)] px-3 text-sm text-ink hover:bg-hover aria-[current=true]:font-semibold">
                                <span class="min-w-0 flex-1 truncate">{{ $organization->name }}</span>
                                @if ($organization->id === $current->id)<x-ui.icon name="check" class="size-4 text-primary-text" />@endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @else
            <div class="flex min-h-12 items-center gap-3 rounded-[var(--radius-md)] border border-hairline px-3">
                <x-public.org-mark :organization="$current" />
                <span class="min-w-0">
                    <span class="block text-xs text-ink-secondary">Organization</span>
                    <span class="block truncate text-sm font-semibold text-ink">{{ $current->name }}</span>
                </span>
            </div>
        @endif
    </div>
@endif
