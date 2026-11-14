{{-- Site footer: every page, the support contacts (config/site.php) and the legal pages. --}}
@props(['scope' => null])
@php
    $support = config('site.support');
    $whatsapp = preg_replace('/\D+/', '', (string) $support['whatsapp']);
@endphp
<footer class="bg-hero text-hero-ink [--focus-ring:var(--hero-focus)]">
    <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
        <div>
            <p class="flex items-center gap-2 text-lg font-semibold"><x-site.mark /> VoteLab</p>
            <p class="mt-3 text-sm text-hero-muted">Voting for Ghana's awards: mobile money or card, every vote a confirmed payment counted once.</p>
        </div>
        <nav aria-label="Voters">
            <h2 class="text-sm font-semibold text-hero-accent">Voters</h2>
            <ul class="mt-3 grid gap-1 text-sm">
                <li><a class="inline-flex min-h-11 items-center hover:underline" href="{{ route('home') }}#live">Vote now</a></li>
                <li><a class="inline-flex min-h-11 items-center hover:underline" href="{{ route('site.results') }}">Results</a></li>
                <li><a class="inline-flex min-h-11 items-center hover:underline" href="{{ route('site.help') }}">Help</a></li>
            </ul>
        </nav>
        <nav aria-label="Organizers and legal">
            <h2 class="text-sm font-semibold text-hero-accent">VoteLab</h2>
            <ul class="mt-3 grid gap-1 text-sm">
                <li><a class="inline-flex min-h-11 items-center hover:underline" href="{{ route('site.organizers') }}">For organizers</a></li>
                <li><a class="inline-flex min-h-11 items-center hover:underline" href="{{ route('site.terms') }}">Terms</a></li>
                <li><a class="inline-flex min-h-11 items-center hover:underline" href="{{ route('site.privacy') }}">Privacy</a></li>
                <li><a class="inline-flex min-h-11 items-center hover:underline" href="{{ route('site.refunds') }}">Refunds</a></li>
            </ul>
        </nav>
        <div>
            <h2 class="text-sm font-semibold text-hero-accent"><a class="hover:underline" href="{{ route('site.contact') }}">Contact</a></h2>
            <ul class="mt-3 grid gap-1 text-sm">
                <li><a class="inline-flex min-h-11 items-center break-all hover:underline" href="mailto:{{ $support['email'] }}">{{ $support['email'] }}</a></li>
                <li><a class="inline-flex min-h-11 items-center hover:underline" href="https://wa.me/{{ $whatsapp }}" rel="noopener">WhatsApp {{ $support['whatsapp'] }}</a></li>
                <li><a class="inline-flex min-h-11 items-center hover:underline" href="tel:{{ preg_replace('/[^\d+]+/', '', (string) $support['phone']) }}">Call {{ $support['phone'] }}</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-hero-raised">
        <p class="mx-auto max-w-6xl px-4 py-5 text-sm text-hero-muted sm:px-6">
            © {{ now()->year }} {{ config('site.legal.name') }}, {{ config('site.legal.country') }}.
            @if ($scope) Voting for {{ $scope->name }} by VoteLab. @endif
            Payments processed securely by Paystack.
        </p>
    </div>
</footer>
