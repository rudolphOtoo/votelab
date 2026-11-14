{{-- /contact: support details (config/site.php). --}}
@php
    $support = config('site.support');
    $whatsapp = preg_replace('/\D+/', '', (string) $support['whatsapp']);
@endphp
<x-site.layout :scope="$scope" title="Contact" :canonical="route('site.contact')" description="Contact VoteLab support.">
    <x-site.band eyebrow="Contact" title="Talk to us" :lead="'For a question about a payment, include your payment reference (it starts with '.config('voting.reference_prefix').'_).'" />

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
        <ul class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['Email', $support['email'], 'mailto:'.$support['email']],
                ['WhatsApp', $support['whatsapp'], 'https://wa.me/'.$whatsapp],
                ['Phone', $support['phone'], 'tel:'.preg_replace('/[^\d+]+/', '', (string) $support['phone'])],
            ] as [$label, $value, $href])
                <li class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-5">
                    <h2 class="text-sm font-semibold text-ink-secondary">{{ $label }}</h2>
                    <a href="{{ $href }}" class="mt-1 inline-flex min-h-11 items-center break-all text-lg font-semibold text-primary-text underline-offset-4 hover:underline" @if ($label === 'WhatsApp') rel="noopener" @endif>{{ $value }}</a>
                </li>
            @endforeach
        </ul>
        <p class="mt-8 max-w-2xl text-ink-secondary">Questions about a nominee, a category or a refund are often fastest with the organizer of the awards, whose details are on their program page. Organizers: <a href="{{ route('site.request-access') }}" class="font-semibold text-primary-text underline">request access</a>.</p>
        <p class="mt-4 text-sm text-ink-secondary">{{ config('site.legal.name') }}, {{ config('site.legal.address') }}, {{ config('site.legal.country') }}.</p>
    </div>
</x-site.layout>
