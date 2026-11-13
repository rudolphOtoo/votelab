{{-- An organization's logo, or its initial on the brand colour. --}}
@props(['organization', 'size' => 'size-8'])
@if ($organization->logo_url)
    <img src="{{ $organization->logo_url }}" alt="" width="32" height="32" class="{{ $size }} shrink-0 rounded-[var(--radius-sm)] bg-subtle object-contain">
@else
    <span class="flex {{ $size }} shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-primary text-sm font-semibold text-primary-fg" aria-hidden="true">{{ mb_strtoupper(mb_substr($organization->name, 0, 1)) }}</span>
@endif
