{{-- Placeholder while loading. shape: text (lines) | block | avatar | photo (4:5). Announces "Loading…" once. --}}
@props(['shape' => 'text', 'lines' => 3])
@php $bar = 'rounded-[var(--radius-sm)] bg-[linear-gradient(90deg,var(--bg-subtle)_25%,var(--bg-hover)_50%,var(--bg-subtle)_75%)] bg-[length:200%_100%] motion-safe:animate-[skeleton_1.4s_ease-in-out_infinite]'; @endphp
<div role="status" {{ $attributes }}>
    <span class="sr-only">Loading…</span>
    @if ($shape === 'avatar')
        <div class="size-10 rounded-full {{ $bar }}" aria-hidden="true"></div>
    @elseif ($shape === 'photo')
        <div class="aspect-[4/5] w-full {{ $bar }}" aria-hidden="true"></div>
    @elseif ($shape === 'block')
        <div class="h-24 w-full {{ $bar }}" aria-hidden="true"></div>
    @else
        <div class="space-y-2" aria-hidden="true">
            @for ($i = 0; $i < $lines; $i++)<div class="h-3.5 {{ $bar }}" style="width: {{ $i === $lines - 1 ? 60 : 100 }}%"></div>@endfor
        </div>
    @endif
</div>
