{{-- /organizers: what organizers get, the fee wording (config/site.php), how to start, and a short FAQ. --}}
<x-site.layout :scope="$scope" title="For organizers" :canonical="route('site.organizers')"
    description="Run your awards on VoteLab: paid and free voting, a live stage screen, audited results and payouts through Paystack.">
    <x-site.band eyebrow="For organizers" title="Run your awards on VoteLab." :lead="config('site.fee')">
        <a href="{{ route('site.request-access') }}" class="mt-6 inline-flex min-h-12 items-center justify-center gap-2 rounded-[var(--radius-md)] bg-hero-accent px-5 font-semibold text-hero-accent-fg hover:brightness-110">Request access</a>
    </x-site.band>

    <section data-reveal aria-labelledby="features-heading" class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
        <h2 id="features-heading" class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">What you get</h2>
        <ul class="mt-8 grid gap-x-10 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['A dashboard for your team', 'Programs, categories and nominees with photos, and roles for owners, admins and staff. Owners and admins sign in with two-factor authentication.'],
                ['A launch checklist', 'Publishing waits until categories, nominees, photos, codes, prices, dates and your payout account are ready.'],
                ['Paid and free voting', 'Paid votes with mobile money or card, free votes with a daily limit per voter, or both in one category.'],
                ['Share kits for WhatsApp', 'Every nominee gets a link, a code and share images sized for statuses and chats.'],
                ['A live stage screen', 'Standings for the venue while voting is open, then a reveal moment for each winner. Hide results whenever you like.'],
                ['Audited results and export', 'Totals checked against the vote ledger, every change in a history log, and a CSV export of the results.'],
                ['Payouts through Paystack', 'Paid votes settle to your organization\'s own Paystack subaccount. Voters pay Paystack\'s processing fee on top of the vote price.'],
                ['Refunds handled clearly', 'A payment confirmed too late to count, after your late-payment window or the reveal, is never counted: it goes to a refund queue for you to settle.'],
            ] as [$title, $text])
                <li class="border-t border-hairline pt-4">
                    <h3 class="font-semibold text-ink">{{ $title }}</h3>
                    <p class="mt-1 text-ink-secondary">{{ $text }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section data-reveal aria-labelledby="faq-heading" class="border-t border-hairline bg-surface">
        <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-16">
            <h2 id="faq-heading" class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Questions organizers ask</h2>
            <div class="mt-6 divide-y divide-hairline border-y border-hairline">
                @foreach ([
                    ['How do we start?', 'Request access with a few details about your event. We set up your organization and invite its first owner, who then invites the rest of the team.'],
                    ['How much does it cost?', config('site.fee')],
                    ['When do we receive the money?', 'Paid votes settle through Paystack to your organization\'s subaccount, on Paystack\'s settlement schedule.'],
                    ['Can we hide the results before the show?', 'Yes. Hide them for any category while voting continues; nobody sees counts or rankings until you show them again or reveal the winner.'],
                    ['What if a payment arrives after voting closes?', 'Payments started before the close still count if they\'re confirmed within your late-payment window and before the reveal. Anything later isn\'t counted and goes to your refund queue.'],
                    ['Can we check the results?', 'Yes. Every vote is in a ledger; the dashboard checks the totals against it and shows any difference, and you can export the results.'],
                ] as [$question, $answer])
                    <details class="group py-4">
                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 font-semibold text-ink">
                            {{ $question }}
                            <x-ui.icon name="chevron-down" class="size-5 shrink-0 transition-transform duration-[var(--duration-fast)] group-open:rotate-180" />
                        </summary>
                        <p class="mt-2 text-ink-secondary">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
            <a href="{{ route('site.request-access') }}" class="mt-8 inline-flex min-h-12 items-center justify-center rounded-[var(--radius-md)] bg-primary px-5 font-semibold text-primary-fg hover:bg-primary-hover">Request access</a>
        </div>
    </section>
</x-site.layout>
