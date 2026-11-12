{{--
  Projector screen (16:9). Large type sized to the viewport, basalt theme (data-theme=stage), high contrast.
  Results markup exists only in the "open" and "revealed" scenes; locked/closed carry the category name only.
--}}
<div wire:poll.5s="poll" data-stage-screen x-data="stageScreen" class="flex min-h-dvh flex-col bg-canvas px-[4vw] py-[3vh] text-ink">
    <header class="flex items-baseline justify-between gap-6">
        <p class="text-[clamp(1rem,1.6vw,2rem)] font-semibold tracking-tight text-ink-secondary">{{ $program->organization->name }} · {{ $program->title }}</p>
        <p class="text-[clamp(0.9rem,1.2vw,1.5rem)] text-ink-tertiary">Vote at {{ preg_replace('#^https?://#', '', url('/'.$program->organization->slug.'/'.$program->slug)) }}</p>
    </header>

    <main class="flex flex-1 flex-col justify-center" aria-live="polite">
        @if ($scene->kind === 'empty')
            <p class="text-center text-[clamp(2rem,5vw,6rem)] font-semibold">{{ $program->title }}</p>
            <p class="mt-4 text-center text-[clamp(1rem,2vw,2.5rem)] text-ink-secondary">Voting opens soon</p>

        @elseif ($scene->kind === 'locked' || $scene->kind === 'closed')
            <div class="text-center" wire:key="locked-{{ $scene->categoryId }}">
                <h1 class="text-[clamp(2rem,5vw,6rem)] font-semibold leading-tight">{{ $scene->categoryName }}</h1>
                <svg aria-hidden="true" viewBox="0 0 24 24" class="mx-auto mt-[4vh] h-[16vh] w-[16vh] text-primary motion-safe:animate-pulse" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" />
                </svg>
                <p class="mx-auto mt-[4vh] max-w-[70vw] text-[clamp(1.25rem,2.6vw,3.5rem)] font-medium text-ink">
                    {{ $scene->kind === 'locked' ? 'Official Blind Voting Phase Active — Scores Hidden Until Finale' : 'Voting closed — results coming up' }}
                </p>
            </div>

        @elseif ($scene->kind === 'open')
            <h1 class="text-[clamp(2rem,4vw,5rem)] font-semibold leading-tight">{{ $scene->categoryName }}</h1>
            <ol class="mt-[3vh] space-y-[1.6vh]">
                @foreach ($scene->bars as $bar)
                    <li wire:key="bar-{{ $bar['id'] }}" class="grid grid-cols-[minmax(0,22vw)_1fr_auto] items-center gap-[2vw]">
                        <p class="truncate text-[clamp(1rem,2vw,2.75rem)] font-medium">{{ $bar['name'] }} <span class="text-ink-tertiary">{{ $bar['code'] }}</span></p>
                        <div class="h-[4.5vh] overflow-hidden rounded-full bg-subtle">
                            <div class="stage-bar h-full origin-left rounded-full bg-primary transition-transform duration-[250ms] ease-[cubic-bezier(0.16,1,0.3,1)] motion-reduce:transition-none" style="transform: scaleX({{ number_format($bar['share'], 4, '.', '') }})"></div>
                        </div>
                        <p class="min-w-[8ch] text-right text-[clamp(1rem,2vw,2.75rem)] font-semibold tabular-nums">{{ number_format($bar['votes']) }}</p>
                    </li>
                @endforeach
            </ol>

        @elseif ($scene->kind === 'revealed')
            <div class="text-center" wire:key="reveal-{{ $scene->categoryId }}" x-data="stageReveal({{ $scene->categoryId }})">
                <h1 class="text-[clamp(1.5rem,3vw,4rem)] font-semibold text-ink-secondary">{{ $scene->categoryName }}</h1>
                <p class="mt-[1vh] text-[clamp(1.25rem,2.4vw,3rem)] font-semibold uppercase tracking-[0.2em] text-primary">{{ $scene->winners > 1 ? 'Joint winners' : 'Winner' }}</p>
                <div class="mt-[3vh] flex flex-wrap items-end justify-center gap-[3vw]">
                    @foreach ($scene->podium as $place)
                        <figure wire:key="podium-{{ $place['id'] }}" @class([
                            'rounded-[var(--radius-lg)] bg-surface p-[1.2vw]',
                            'ring-4 ring-[#FBBF24] shadow-[0_0_60px_rgba(251,191,36,0.45)] motion-safe:animate-[stage-win_600ms_cubic-bezier(0.34,1.56,0.64,1)_both]' => $place['winner'],
                            'opacity-80' => !$place['winner'],
                        ])>
                            @if ($place['photo'])
                                <img src="{{ $place['photo'] }}" alt="" class="{{ $place['winner'] ? 'h-[42vh]' : 'h-[26vh]' }} aspect-[4/5] rounded-[var(--radius-md)] object-cover">
                            @endif
                            <figcaption class="mt-[1vh]">
                                <span class="block text-[clamp(1.25rem,{{ $place['winner'] ? '3vw' : '2vw' }},4rem)] font-semibold">{{ $place['name'] }}</span>
                                <span class="block text-[clamp(1rem,1.6vw,2.25rem)] tabular-nums text-ink-secondary">{{ number_format($place['votes']) }} votes</span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        @endif
    </main>
</div>
