{{-- /help: answers for voters. Each one matches how VoteLab behaves. --}}
<x-site.layout :scope="$scope" title="Help" :canonical="route('site.help')"
    description="How to vote on VoteLab, paying with mobile money, your payment reference, and what to do if a vote didn't count.">
    <x-site.band eyebrow="Help" title="Voting on VoteLab" lead="Short answers to what voters ask most." />

    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
        <div class="divide-y divide-hairline border-y border-hairline">
            @foreach ([
                ['How do I vote?', 'Open the link your nominee shared, or search their code or name on the VoteLab homepage. Choose how many votes, check the total, then pay with mobile money or card. Some categories also have free votes: one or more a day, set by the organizer.'],
                ['How does paying with mobile money work?', 'After you choose your votes you go to Paystack\'s secure checkout. Pick mobile money, enter your number, and approve the prompt that appears on your phone. Your votes are counted as soon as Paystack confirms the payment.'],
                ['What is my payment reference?', 'Every payment gets a reference, shown on the page you see after paying (it starts with '.config('voting.reference_prefix').'_). Keep it: it\'s how the organizer finds your payment if you have a question.'],
                ['My vote didn\'t count. What do I do?', 'If you paid and the page still says it\'s confirming, wait a few minutes: mobile money can take a moment, and we keep checking with Paystack. If your votes still don\'t show, contact the organizer, or us, with your payment reference.'],
                ['Why can\'t I see the results?', 'Organizers can hide the results before the show, so the winner is a surprise. Voting continues while results are hidden, and the counts appear again when the organizer reveals the winner.'],
                ['Can I get a refund?', 'Votes are final once counted and can\'t be refunded. If a payment was received but couldn\'t be counted (for example, voting closed before it was confirmed), the organizer arranges a refund.'],
                ['Will I get a receipt?', 'Paystack may email you a receipt for the payment. Your payment reference is shown on the page after you pay either way.'],
            ] as [$question, $answer])
                <details class="group py-4" @if ($loop->first) open @endif>
                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 font-semibold text-ink">
                        {{ $question }}
                        <x-ui.icon name="chevron-down" class="size-5 shrink-0 transition-transform duration-[var(--duration-fast)] group-open:rotate-180" />
                    </summary>
                    <p class="mt-2 text-ink-secondary">{{ $answer }}</p>
                </details>
            @endforeach
        </div>
        <p class="mt-8 text-ink-secondary">Still stuck? <a href="{{ route('site.contact') }}" class="font-semibold text-primary-text underline">Contact us</a> and include your payment reference.</p>
    </div>
</x-site.layout>
