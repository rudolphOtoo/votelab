{{-- Money from integer pesewas, formatted in one place (App\Support\Money). Tabular figures keep totals steady. --}}
@props(['pesewas'])
<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ \App\Support\Money::format((int) $pesewas) }}</span>
