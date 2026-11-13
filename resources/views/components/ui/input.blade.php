@props(['type' => 'text', 'invalid' => false])
<input type="{{ $type }}" @if ($invalid) aria-invalid="true" @endif {{ $attributes->merge(['class' => \App\Support\Design\ControlClasses::BASE.' min-h-12']) }}>
