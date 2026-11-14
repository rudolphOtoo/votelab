{{-- "Closes in 2d 4h" (kept current by resources/js/home.js) and the closing time in Accra. --}}
@if ($card['closesAt'])
    <span class="font-semibold tabular-nums" data-closes-at="{{ $card['closesAt']->toIso8601String() }}">{{ $card['closesIn'] }}</span>
    <span class="basis-full text-[var(--text-secondary)]">{{ $card['closesOn'] }} (Accra time)</span>
@else
    <span class="font-semibold">Voting open</span>
@endif
