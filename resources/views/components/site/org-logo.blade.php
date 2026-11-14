{{-- An organization's logo from cached site data (a URL and a name), or its initial. --}}
@props(['logo' => null, 'name', 'size' => 'size-12'])
@if ($logo)
    <img src="{{ $logo }}" alt="" width="48" height="48" loading="lazy" decoding="async" class="{{ $size }} shrink-0 rounded-[var(--radius-sm)] bg-subtle object-contain">
@else
    <span class="flex {{ $size }} shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-primary text-lg font-semibold text-primary-fg" aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
@endif
