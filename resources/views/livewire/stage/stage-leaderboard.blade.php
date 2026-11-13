{{--
  Projector screen (16:9, read from the back of a hall). Viewport-scaled type, basalt theme (data-theme=stage),
  the organization's colour made readable on basalt (StageBranding::accent) and its logo. Results markup exists
  only in the "open" and "revealed" scenes; locked/closed carry the category name only (D-104).
--}}
@php
    $organization = $program->organization;
    $voteUrl = preg_replace('#^https?://#', '', url('/'.$organization->slug.'/'.$program->slug));
@endphp

<div wire:poll.5s="poll" data-stage-screen x-data="stageScreen"
     style="--stage-accent: {{ $accent }}; --brand: {{ $accent }};"
     class="flex min-h-dvh flex-col bg-canvas px-[4vw] py-[3vh] text-ink">
    <header class="flex items-center justify-between gap-[3vw]">
        <div class="flex min-w-0 items-center gap-[1.2vw]">
            @if ($organization->logo_url)
                <img src="{{ $organization->logo_url }}" alt="" class="h-[7vh] w-auto max-w-[12vw] shrink-0 object-contain">
            @else
                <span class="flex size-[7vh] shrink-0 items-center justify-center rounded-[var(--radius-md)] bg-[var(--stage-accent)] text-[3.5vh] font-bold text-[#121316]" aria-hidden="true">{{ mb_strtoupper(mb_substr($organization->name, 0, 1)) }}</span>
            @endif
            <div class="min-w-0">
                <p class="truncate text-[clamp(1rem,1.7vw,2.25rem)] font-semibold tracking-tight text-ink">{{ $program->title }}</p>
                <p class="truncate text-[clamp(0.85rem,1.1vw,1.5rem)] text-ink-secondary">{{ $organization->name }}</p>
            </div>
        </div>
        <div class="shrink-0 text-right">
            <p class="text-[clamp(0.85rem,1.1vw,1.5rem)] text-ink-secondary">Vote on your phone</p>
            <p class="text-[clamp(1.1rem,1.9vw,2.5rem)] font-semibold tracking-tight text-[var(--stage-accent)]">{{ $voteUrl }}</p>
        </div>
    </header>

    <main class="flex flex-1 flex-col justify-center py-[2vh]" aria-live="polite">
        {{-- Confetti draws here (inside the page's main landmark), above everything, never catching clicks. --}}
        <canvas x-ref="confetti" data-stage-confetti aria-hidden="true" class="pointer-events-none fixed inset-0 z-[var(--z-toast)] size-full" wire:ignore></canvas>
        @if ($scene->kind === 'empty')
            <div class="text-center">
                <h1 class="text-[clamp(2rem,5.5vw,7rem)] font-semibold leading-tight tracking-tight">{{ $program->title }}</h1>
                <p class="mt-[3vh] text-[clamp(1.25rem,2.4vw,3rem)] text-ink-secondary">Voting opens soon</p>
            </div>

        @elseif ($scene->kind === 'locked' || $scene->kind === 'closed')
            <div class="text-center" wire:key="locked-{{ $scene->categoryId }}">
                <h1 class="text-[clamp(2rem,5vw,6.5rem)] font-semibold leading-tight tracking-tight">{{ $scene->categoryName }}</h1>
                @if ($scene->kind === 'locked')
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="mx-auto mt-[4vh] size-[16vh] text-[var(--stage-accent)] motion-safe:animate-pulse" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" />
                    </svg>
                @else
                    <svg aria-hidden="true" viewBox="0 0 24 24" class="mx-auto mt-[4vh] size-[16vh] text-[var(--stage-accent)]" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M6 2h12M6 22h12M7 2c0 6 10 6 10 10S7 16 7 22M17 2c0 6-10 6-10 10s10 4 10 10" />
                    </svg>
                @endif
                <p class="mx-auto mt-[4vh] max-w-[72vw] text-[clamp(1.25rem,2.7vw,3.75rem)] font-semibold leading-snug text-ink">
                    {{ $scene->kind === 'locked' ? 'Official Blind Voting Phase Active — Scores Hidden Until Finale' : 'Voting closed — results coming up' }}
                </p>
                @if ($scene->kind === 'locked')
                    <p class="mt-[2vh] text-[clamp(1rem,1.8vw,2.5rem)] text-ink-secondary">Every vote still counts. Vote at <span class="font-semibold text-[var(--stage-accent)]">{{ $voteUrl }}</span></p>
                @endif
            </div>

        @elseif ($scene->kind === 'open')
            @php $total = array_sum(array_column($scene->bars, 'votes')); @endphp
            <div class="flex flex-wrap items-end justify-between gap-x-[3vw] gap-y-[1vh]">
                <h1 class="text-[clamp(2rem,4.2vw,5.5rem)] font-semibold leading-tight tracking-tight">{{ $scene->categoryName }}</h1>
                <p class="flex items-center gap-[0.8vw] text-[clamp(1rem,1.6vw,2.25rem)] text-ink-secondary">
                    <span class="relative flex size-[1.4vh]"><span class="absolute inline-flex size-full rounded-full bg-[var(--stage-accent)] opacity-60 motion-safe:animate-ping"></span><span class="relative inline-flex size-full rounded-full bg-[var(--stage-accent)]"></span></span>
                    Live · <span class="font-semibold tabular-nums text-ink">{{ number_format($total) }}</span> votes
                </p>
            </div>
            <ol class="mt-[3.5vh] space-y-[1.4vh]">
                @foreach ($scene->bars as $bar)
                    <li wire:key="bar-{{ $bar['id'] }}" class="grid grid-cols-[4vw_minmax(0,34vw)_1fr_auto] items-center gap-[1.6vw]">
                        <span class="text-right text-[clamp(1.25rem,2.5vw,3.25rem)] font-bold tabular-nums {{ $loop->first ? 'text-[var(--stage-accent)]' : 'text-ink-tertiary' }}">{{ $loop->iteration }}</span>
                        <p class="flex min-w-0 items-baseline gap-[0.8vw] leading-tight">
                            <span class="truncate text-[clamp(1.1rem,2.1vw,2.9rem)] font-semibold">{{ $bar['name'] }}</span>
                            <span class="shrink-0 font-mono text-[clamp(0.9rem,1.4vw,1.9rem)] font-semibold tracking-wider text-[var(--stage-accent)]">{{ $bar['code'] }}</span>
                        </p>
                        <div class="h-[4.4vh] overflow-hidden rounded-full bg-subtle">
                            <div class="stage-bar h-full origin-left rounded-full bg-[var(--stage-accent)] transition-transform duration-[700ms] ease-[cubic-bezier(0.16,1,0.3,1)] motion-reduce:transition-none {{ $loop->first ? '' : 'opacity-70' }}" style="transform: scaleX({{ number_format($bar['share'], 4, '.', '') }})"></div>
                        </div>
                        <p class="min-w-[7ch] text-right text-[clamp(1.1rem,2.1vw,2.9rem)] font-semibold tabular-nums">{{ number_format($bar['votes']) }}</p>
                    </li>
                @endforeach
            </ol>

        @elseif ($scene->kind === 'revealed')
            <div class="text-center" wire:key="reveal-{{ $scene->categoryId }}" x-data="stageReveal({{ $scene->categoryId }}, @js($accent))">
                <h1 class="text-[clamp(1.5rem,3vw,4rem)] font-semibold text-ink-secondary">{{ $scene->categoryName }}</h1>
                {{-- A short pause before the podium (skipped under reduced motion and after the first showing). --}}
                <p x-show="phase === 'intro'" x-transition.opacity.duration.500ms class="mt-[12vh] text-[clamp(2rem,5vw,6.5rem)] font-semibold tracking-tight text-ink" style="display: none">And the winner is…</p>
                <div x-show="phase === 'show'" x-transition:enter="transition duration-[900ms] ease-[cubic-bezier(0.16,1,0.3,1)]" x-transition:enter-start="opacity-0 translate-y-[4vh] scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100">
                    <p class="mt-[0.5vh] text-[clamp(1.25rem,2.4vw,3.25rem)] font-bold uppercase tracking-[0.25em] text-[var(--stage-accent)]">{{ $scene->winners > 1 ? 'Joint winners' : 'Winner' }}</p>
                    <div class="mt-[2.5vh] flex flex-wrap items-end justify-center gap-[3vw]">
                        @foreach ($scene->podium as $place)
                            <figure wire:key="podium-{{ $place['id'] }}" @if ($place['winner']) data-stage-winner @endif @class([
                                'rounded-[var(--radius-lg)] bg-surface p-[1.2vw]',
                                'min-w-[26vw]' => $place['winner'],
                                'min-w-[17vw]' => !$place['winner'],
                                'ring-4 ring-[var(--stage-accent)] shadow-[0_0_80px_color-mix(in_srgb,var(--stage-accent)_45%,transparent)] motion-safe:animate-[stage-win_700ms_cubic-bezier(0.34,1.56,0.64,1)_both]' => $place['winner'],
                            ])>
                                @if ($place['photo'])
                                    <img src="{{ $place['photo'] }}" alt="" class="{{ $place['winner'] ? 'h-[38vh]' : 'h-[24vh]' }} mx-auto aspect-[4/5] rounded-[var(--radius-md)] object-cover">
                                @endif
                                <figcaption class="mt-[1.2vh]">
                                    {{-- Full class names (not built from parts) so Tailwind generates both sizes. --}}
                                    <span class="block font-semibold leading-tight {{ $place['winner'] ? 'text-[clamp(1.4rem,2.6vw,3.5rem)]' : 'text-[clamp(1.1rem,1.8vw,2.5rem)]' }}">{{ $place['name'] }}</span>
                                    <span class="block text-[clamp(1rem,1.6vw,2.25rem)] tabular-nums text-ink-secondary">{{ number_format($place['votes']) }} votes</span>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </main>

    <footer class="flex items-center justify-between text-[clamp(0.75rem,0.9vw,1.25rem)] text-ink-tertiary">
        <span>@if ($scene->kind === 'open') Results update live @endif</span>
        <span>Voting by {{ config('app.name') }}</span>
    </footer>
</div>
