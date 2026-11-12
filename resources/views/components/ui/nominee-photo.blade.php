{{--
  A nominee portrait at 4:5 with explicit dimensions (no layout shift). Lazy by default; eager for the one
  above-the-fold image. Without a photo, shows initials on a tint.
--}}
@props(['src' => null, 'name', 'eager' => false, 'width' => 400, 'sizes' => '(min-width: 1024px) 18rem, (min-width: 640px) 40vw, 90vw'])
@php $height = (int) round($width * 5 / 4); @endphp
@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" width="{{ $width }}" height="{{ $height }}" sizes="{{ $sizes }}" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" @if ($eager) fetchpriority="high" @endif
         {{ $attributes->merge(['class' => 'aspect-[4/5] w-full rounded-[var(--radius-md)] bg-subtle object-cover']) }}>
@else
    <div role="img" aria-label="{{ $name }}" {{ $attributes->merge(['class' => 'flex aspect-[4/5] w-full items-center justify-center rounded-[var(--radius-md)] bg-primary-subtle text-4xl font-semibold text-primary-text']) }}>
        {{ mb_strtoupper(mb_substr($name, 0, 1)) }}
    </div>
@endif
