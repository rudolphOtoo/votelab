@props(['invalid' => false, 'rows' => 4])
<textarea rows="{{ $rows }}" @if ($invalid) aria-invalid="true" @endif {{ $attributes->merge(['class' => \App\Support\Design\ControlClasses::BASE.' py-3']) }}>{{ $slot }}</textarea>
