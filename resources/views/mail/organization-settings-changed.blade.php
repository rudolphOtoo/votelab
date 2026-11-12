<x-mail::message>
# {{ $organization->name }}: settings changed

**{{ $changedBy }}** changed these settings on {{ $changedAt->timezone(config('app.timezone'))->format('j M Y, H:i') }} (GMT):

@foreach ($changes as $change)
- {{ $change }}
@endforeach

You're receiving this because you're an owner. If you didn't expect this change, sign in now, check the
organization's members and settings, and change your password.

{{ config('app.name') }}
</x-mail::message>
