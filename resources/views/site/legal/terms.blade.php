<x-site.legal :scope="$scope" title="Terms of use" route="site.terms" description="The terms for voting and organizing awards on VoteLab.">
    <h2>What VoteLab is</h2>
    <p>VoteLab lets organizers in {{ config('site.legal.country') }} run awards with paid and free voting, and lets the public vote. Each awards program is run by its organizer, who decides its categories, nominees, prices, voting dates and when results are shown.</p>

    <h2>Voting</h2>
    <ul>
        <li>Paid votes are counted only after Paystack confirms the payment for the exact amount shown to you before you paid.</li>
        <li>Votes are final once counted and can't be refunded or moved to another nominee.</li>
        <li>Free votes, where a category offers them, are limited per voter per day as set by the organizer. We use a device cookie and keyed hashes of your network details to apply that limit.</li>
        <li>You must not try to get around voting limits, automate voting, or interfere with the service. We may refuse votes that break these rules.</li>
    </ul>

    <h2>Payments</h2>
    <p>Payments are processed by Paystack. The amount you pay includes Paystack's processing fee on top of the vote price, and you see the total before you pay. VoteLab never sees your card or mobile money PIN.</p>

    <h2>Results</h2>
    <p>Organizers may hide results before their show. While results are hidden, VoteLab shows no counts or rankings for that category. The official result is the one revealed by the organizer.</p>

    <h2>Organizers</h2>
    <p>Organizers are responsible for their programs, nominees and the content they publish, and receive paid votes through their own Paystack subaccount. Organizer accounts are by invitation; see <a href="{{ route('site.organizers') }}">For organizers</a>.</p>

    <h2>Refunds</h2>
    <p>See the <a href="{{ route('site.refunds') }}">refund policy</a>.</p>

    <h2>Changes and contact</h2>
    <p>We may update these terms; the date above shows the latest version. Questions: <a href="{{ route('site.contact') }}">contact us</a>. These terms are governed by the laws of {{ config('site.legal.country') }}.</p>
</x-site.legal>
