{{-- Organizer pages: top bar, two-factor deadline banner (set by RequireTwoFactorForManagers), content. --}}
<x-layouts::app :title="$title ?? null" bundle="admin">
    <header class="border-b border-hairline bg-surface">
        <nav aria-label="Organizer" class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
            <a href="{{ route('dashboard') }}" class="mr-auto text-sm font-semibold tracking-tight text-ink">{{ config('app.name') }}</a>
            <a href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif class="min-h-11 content-center text-sm text-ink-secondary hover:text-ink aria-[current=page]:font-medium aria-[current=page]:text-ink">Dashboard</a>
            <a href="{{ route('dashboard.security') }}" @if (request()->routeIs('dashboard.security')) aria-current="page" @endif class="min-h-11 content-center text-sm text-ink-secondary hover:text-ink aria-[current=page]:font-medium aria-[current=page]:text-ink">Security</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="min-h-11 text-sm text-ink-secondary hover:text-ink">Sign out</button>
            </form>
        </nav>
    </header>

    @isset($twoFactorDeadline)
        <div role="status" class="border-b border-warning/30 bg-warning/10">
            <p class="mx-auto max-w-5xl px-4 py-3 text-sm text-ink">
                Owners and admins must turn on two-factor authentication by
                <strong>{{ $twoFactorDeadline->timezone(config('app.timezone'))->format('j M Y') }}</strong>.
                <a href="{{ route('dashboard.security') }}" class="font-medium underline underline-offset-4">Turn it on now</a>
            </p>
        </div>
    @endisset

    <main class="mx-auto w-full max-w-5xl px-4 py-8">
        @php
            $statusMessages = [
                'two-factor-authentication-enabled' => 'Scan the QR code, then enter a code to finish turning on two-factor.',
                'two-factor-authentication-confirmed' => 'Two-factor authentication is on. Your other sessions were signed out.',
                'two-factor-authentication-disabled' => 'Two-factor authentication is off.',
                'recovery-codes-generated' => 'New recovery codes generated. The old ones no longer work.',
                'password-updated' => 'Password changed. Your other sessions were signed out.',
                'passkey-registered' => 'Passkey added.',
                'passkey-deleted' => 'Passkey removed.',
            ];
            $status = session('status');
        @endphp
        @if (is_string($status))
            <p role="status" class="mb-6 rounded-[var(--radius-md)] bg-subtle p-3 text-sm text-ink">{{ $statusMessages[$status] ?? $status }}</p>
        @endif

        {{ $slot }}
    </main>
</x-layouts::app>
