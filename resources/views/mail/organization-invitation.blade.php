<x-mail::message>
# Join {{ $organizationName }} on VoteLab

You've been invited to help run **{{ $organizationName }}** as **{{ $role }}**.

<x-mail::button :url="$acceptUrl">
Accept invitation
</x-mail::button>

This link works once and expires on {{ $expiresAt->timezone(config('app.timezone'))->format('j M Y, H:i') }} (GMT).
If you weren't expecting it, you can ignore this email.

{{ config('app.name') }}
</x-mail::message>
