<x-site.legal :scope="$scope" title="Refund policy" route="site.refunds" description="When a payment made on VoteLab is refunded.">
    <h2>Votes are final once counted</h2>
    <p>When your payment is confirmed and your votes are counted, they can't be refunded, cancelled or moved to another nominee.</p>

    <h2>When you get your money back</h2>
    <p>A payment is refunded only when it was received but its votes couldn't be counted. That happens when Paystack confirms the payment too late: after the category closed and its late-payment window ended, or after the winner was revealed. These payments are never counted.</p>
    <ul>
        <li>The organizer of the awards arranges the refund, outside VoteLab, to the account you paid from.</li>
        <li>Keep your payment reference (it starts with {{ config('voting.reference_prefix') }}_): it identifies your payment.</li>
        <li>If you haven't heard back, contact the organizer, or <a href="{{ route('site.contact') }}">contact us</a> with your reference.</li>
    </ul>

    <h2>Failed or unfinished payments</h2>
    <p>If a payment fails or you leave the checkout, no votes are counted and you aren't charged. If you were charged but the page still shows the payment as pending, wait a few minutes: we keep checking with Paystack, and a confirmed payment is counted or refunded as above.</p>
</x-site.legal>
