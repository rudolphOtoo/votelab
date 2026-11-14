<x-mail::message>
# New request for access

**{{ $request->name }}** from **{{ $request->organization }}** would like to run an awards program on VoteLab.

- **Email:** {{ $request->email }}
- **Phone:** {{ $request->phone }}
- **Event:** {{ $request->eventTypeLabel() }}
- **Expected date:** {{ $request->expected_date?->format('j M Y') ?? 'Not given' }}

@if ($request->message)
**Message:**

{{ $request->message }}
@endif

Reply to this email to answer them directly. The request is deleted {{ config('site.access_request_retention_days') }} days after it arrived.

{{ config('app.name') }}
</x-mail::message>
