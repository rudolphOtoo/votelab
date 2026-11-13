{{-- Surface container. padding: none | md | lg. Optional header / footer slots. --}}
@props(['padding' => 'md', 'as' => 'div'])
@php $pad = ['none' => '', 'md' => 'p-4 sm:p-5', 'lg' => 'p-5 sm:p-6'][$padding] ?? 'p-4 sm:p-5'; @endphp
<{{ $as }} {{ $attributes->merge(['class' => 'rounded-[var(--radius-lg)] border border-hairline bg-surface shadow-card']) }}>
    @isset($header)<div class="border-b border-hairline px-4 py-3 sm:px-5">{{ $header }}</div>@endisset
    <div class="{{ $pad }}">{{ $slot }}</div>
    @isset($footer)<div class="border-t border-hairline px-4 py-3 sm:px-5">{{ $footer }}</div>@endisset
</{{ $as }}>
