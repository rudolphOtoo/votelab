{{--
  A nominee portrait at 4:5 with explicit dimensions (no layout shift). Lazy by default; eager for the one
  above-the-fold image. Without a photo, shows initials on a tint. `srcset` (with `sizes`) only when smaller
  variants exist; today each nominee has one 800×1000 WebP.
--}}
@props(['src' => null, 'name', 'eager' => false, 'width' => 400, 'srcset' => null, 'sizes' => '(min-width: 1024px) 14rem, (min-width: 640px) 30vw, 46vw', 'alt' => null])
@php
    $height = (int) round($width * 5 / 4);
    $radius = str_contains((string) $attributes->get('class'), 'rounded') ? '' : ' rounded-[var(--radius-md)]';
@endphp
@if ($src)
    <img src="{{ $src }}" alt="{{ $alt ?? $name }}" width="{{ $width }}" height="{{ $height }}" @if ($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" @if ($eager) fetchpriority="high" @endif
         {{ $attributes->merge(['class' => 'aspect-[4/5] w-full bg-subtle object-cover' . $radius]) }}>
@else
    <div @if (($alt ?? $name) === '') aria-hidden="true" @else role="img" aria-label="{{ $alt ?? $name }}" @endif {{ $attributes->merge(['class' => 'flex aspect-[4/5] w-full items-center justify-center bg-primary-subtle text-4xl font-semibold text-primary-text' . $radius]) }}>
        {{ mb_strtoupper(mb_substr($name, 0, 1)) }}
    </div>
@endif
