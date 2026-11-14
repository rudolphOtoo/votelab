{{-- Hero art (direction A): a sparse diamond lattice, a gold spotlight and soft beams, one slow light sweep. Original and
     decorative. The spotlight peaks at 20% gold, so supporting text stays above 5.5:1 at its brightest point; every
     movement stops under reduced motion. --}}
<svg class="pointer-events-none absolute inset-0 -z-10 h-full w-full" viewBox="0 0 1440 720" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
    <defs>
        <radialGradient id="site-spot" cx="72%" cy="8%" r="70%">
            <stop offset="0" stop-color="var(--gold)" stop-opacity=".2" />
            <stop offset=".45" stop-color="var(--gold)" stop-opacity=".05" />
            <stop offset="1" stop-color="var(--hero-bg)" stop-opacity="0" />
        </radialGradient>
        <linearGradient id="site-beam" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="var(--gold)" stop-opacity=".18" />
            <stop offset="1" stop-color="var(--gold)" stop-opacity="0" />
        </linearGradient>
        <pattern id="site-lattice" width="36" height="36" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
            <rect x="16" y="16" width="4" height="4" fill="var(--gold)" fill-opacity=".14" />
        </pattern>
    </defs>
    <rect width="1440" height="720" fill="url(#site-lattice)" />
    <rect width="1440" height="720" fill="url(#site-spot)" class="motion-safe:animate-[site-glow_6s_ease-in-out_infinite]" />
    <polygon points="980,0 1060,0 1240,720 760,720" fill="url(#site-beam)" />
    <polygon points="1120,0 1180,0 1440,620 1440,720 1180,720" fill="url(#site-beam)" opacity=".7" />
    <polygon points="840,0 880,0 620,720 420,720" fill="url(#site-beam)" opacity=".45" />
</svg>
<div class="pointer-events-none absolute inset-y-0 -left-1/3 -z-10 w-1/3 bg-gradient-to-r from-transparent via-gold/10 to-transparent motion-safe:animate-[site-sweep_9s_ease-in-out_infinite]" aria-hidden="true"></div>
