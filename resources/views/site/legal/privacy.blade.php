@php
    $days = static fn(string $channel): int => (int) config("logging.channels.{$channel}.max_files");
@endphp
<x-site.legal :scope="$scope" title="Privacy notice" route="site.privacy" description="What personal data VoteLab collects, why, and how long it keeps it.">
    <p>This notice explains what we collect when you vote, organize awards or contact us, and your rights under {{ config('site.legal.country') }}'s Data Protection Act, 2012 (Act 843). Data controller: {{ config('site.legal.name') }}, {{ config('site.legal.address') }}. [Data Protection Commission registration number]</p>

    <h2>When you vote</h2>
    <ul>
        <li><strong>Your payment.</strong> Paystack processes it. We keep the amount, the votes, the nominee, a payment reference and its status. We never see card details or PINs.</li>
        <li><strong>Your phone number</strong> (from mobile money). We store only a keyed hash of it and its last three digits, so an organizer can find your payment if you ask; the full number is never stored.</li>
        <li><strong>Your email</strong>, only if you give one for a receipt. Paystack uses it to send the receipt; we keep it with the payment so it can be found if you ask. Without one, Paystack receives a one-off placeholder address and we store nothing.</li>
        <li><strong>Free-vote and abuse limits.</strong> A device cookie (a random ID, kept one year) and keyed hashes of your network address and browser. We can't turn these hashes back into your address.</li>
    </ul>

    <h2>Organizers and people who request access</h2>
    <ul>
        <li>Organizer accounts: name, email, a hashed password, and two-factor or passkey details; an audit log of changes made in the dashboard.</li>
        <li>Request-access forms: your name, organization, email, phone and message, used only to reply, and deleted {{ config('site.access_request_retention_days') }} days after you send them.</li>
    </ul>

    <h2>Cookies</h2>
    <p>We use a session cookie and a security (CSRF) cookie so the site works and stays secure, and the device cookie described above. We don't use advertising or third-party tracking cookies.</p>

    <h2>Service providers</h2>
    <ul>
        <li>Paystack, to process payments.</li>
        <li>An error-tracking service (Sentry), to find and fix faults. Phone numbers and email addresses are removed from what we send it.</li>
        <li>Our hosting and email providers. [List providers and where they store data]</li>
    </ul>

    <h2>How long we keep it</h2>
    <ul>
        <li>Payment and vote records: as long as the awards program's results must be checked and accounted for. [Retention period to be confirmed]</li>
        <li>Operational logs: payments {{ $days('payments') }} days, voting {{ $days('voting') }} days, alerts {{ $days('alerts') }} days, organizer audit log {{ $days('audit') }} days.</li>
        <li>Request-access submissions: {{ config('site.access_request_retention_days') }} days.</li>
    </ul>

    <h2>Your rights</h2>
    <p>You can ask what we hold about you, and ask us to correct or delete it where the law allows (payment records may need to be kept). <a href="{{ route('site.contact') }}">Contact us</a>; quote your payment reference if it's about a payment. You can also complain to the Data Protection Commission of Ghana.</p>
</x-site.legal>
