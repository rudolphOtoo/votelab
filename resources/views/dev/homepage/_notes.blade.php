{{-- Dev preview only: the direction's palette (every pair measured for WCAG 2.2 AA), type and reasoning. --}}
<section class="border-t border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-primary)]" aria-labelledby="notes-heading">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        <p class="text-sm text-[var(--text-secondary)]">
            Dev preview, Step A of the homepage brief.
            @foreach ($directions as $d)
                <a class="font-medium underline" href="{{ route('dev.homepage', $d['key']) }}" @if ($d['key'] === $key) aria-current="page" @endif>{{ strtoupper($d['key']) }} · {{ $d['name'] }}</a>@if (!$loop->last) | @endif
            @endforeach
            · <a class="font-medium underline" href="?empty=1">empty state</a>
            · <a class="font-medium underline" href="?repeat=3">grid preview</a>
        </p>
        <details class="mt-3">
            <summary id="notes-heading" class="min-h-11 cursor-pointer py-2 font-semibold">Design notes: {{ $design['name'] }}</summary>
            <p class="mt-2 max-w-prose">{{ $design['reasoning'] }}</p>
            <p class="mt-2 max-w-prose text-[var(--text-secondary)]"><strong class="text-[var(--text-primary)]">Type:</strong> {{ $design['type'] }}</p>
            <ul class="mt-4 flex flex-wrap gap-2" aria-label="Palette">
                @foreach ($design['colors'] as $name => $hex)
                    <li class="flex items-center gap-2 rounded-[var(--radius-sm)] border border-[var(--border-hairline)] px-2 py-1 text-xs">
                        <span class="size-4 rounded-full border border-[var(--border-hairline)]" style="background: {{ $hex }}"></span>{{ $name }} {{ $hex }}
                    </li>
                @endforeach
            </ul>
            <table class="mt-4 w-full text-left text-sm">
                <caption class="sr-only">Contrast of every colour pair used</caption>
                <thead><tr><th scope="col" class="py-1">Pair</th><th scope="col">Ratio</th><th scope="col">AA minimum</th></tr></thead>
                <tbody>
                    @foreach ($pairs as $pair)
                        <tr class="border-t border-[var(--border-hairline)]">
                            <td class="py-1">{{ $pair['usage'] }}</td>
                            <td class="tabular-nums">{{ number_format($pair['ratio'], 2) }}:1</td>
                            <td class="tabular-nums">{{ $pair['minimum'] }}:1 {{ $pair['ratio'] >= $pair['minimum'] ? 'pass' : 'FAIL' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    </div>
</section>
