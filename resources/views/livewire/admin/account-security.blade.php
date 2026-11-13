<div class="max-w-2xl">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Security</h1>

    {{-- Two-factor --}}
    <section class="mt-8 rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card" aria-labelledby="two-factor-heading">
        <h2 id="two-factor-heading" class="text-lg font-semibold text-ink">Two-factor authentication</h2>

        @if ($twoFactorOn)
            <p class="mt-2 text-sm text-ink-secondary">On. You'll be asked for a code from your authenticator app when you sign in.</p>

            @if ($showRecoveryCodes)
                <div class="mt-4 rounded-[var(--radius-md)] bg-subtle p-4">
                    <p class="text-sm font-medium text-ink">Recovery codes</p>
                    <p class="mt-1 text-sm text-ink-secondary">Store these somewhere safe. Each works once if you lose your device.</p>
                    <ul class="mt-3 grid grid-cols-1 gap-1 font-mono text-sm text-ink sm:grid-cols-2">
                        @foreach ($user->recoveryCodes() as $code)
                            <li>{{ $code }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}" class="sm:w-auto">
                    @csrf
                    <x-form.button variant="secondary" class="sm:w-auto">New recovery codes</x-form.button>
                </form>
                <form method="POST" action="{{ route('two-factor.disable') }}" class="sm:w-auto">
                    @csrf
                    @method('DELETE')
                    <x-form.button variant="danger" class="sm:w-auto">Turn off</x-form.button>
                </form>
            </div>
            @if ($required)
                <p class="mt-3 text-sm text-ink-secondary">Owners and admins need two-factor: turning it off locks the dashboard until it's back on.</p>
            @endif
        @elseif ($twoFactorPending)
            <p class="mt-2 text-sm text-ink-secondary">Scan this QR code with your authenticator app, then enter the 6-digit code it shows.</p>
            <div class="mt-4 inline-block rounded-[var(--radius-md)] bg-white p-3" role="img" aria-label="QR code for your authenticator app">{!! $qrCodeSvg !!}</div>
            <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 max-w-xs" novalidate>
                @csrf
                <x-form.field name="code" label="Code from your app" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" error-bag="confirmTwoFactorAuthentication" required autofocus />
                <x-form.button class="mt-4">Turn on two-factor</x-form.button>
            </form>
        @else
            <p class="mt-2 text-sm text-ink-secondary">
                Off. Add a second step to signing in with an authenticator app.
                @if ($deadline)
                    <strong class="text-ink">Required for owners and admins by {{ $deadline->timezone(config('app.timezone'))->format('j M Y') }}.</strong>
                @endif
            </p>
            <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-4">
                @csrf
                <x-form.button class="sm:w-auto">Set up two-factor</x-form.button>
            </form>
        @endif
    </section>

    {{-- Passkeys --}}
    <section class="mt-6 rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card" aria-labelledby="passkeys-heading">
        <h2 id="passkeys-heading" class="text-lg font-semibold text-ink">Passkeys</h2>
        <p class="mt-2 text-sm text-ink-secondary">Sign in with your device's fingerprint, face or screen lock instead of a password.</p>

        <ul class="mt-4 divide-y divide-hairline">
            @forelse ($passkeys as $passkey)
                <li wire:key="passkey-{{ $passkey->id }}" class="flex items-center gap-3 py-3">
                    <div class="mr-auto">
                        <p class="text-sm font-medium text-ink">{{ $passkey->name }}</p>
                        <p class="text-sm text-ink-secondary">Last used {{ $passkey->last_used_at?->diffForHumans() ?? 'never' }}</p>
                    </div>
                    <form method="POST" action="{{ route('passkey.destroy', $passkey) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="min-h-11 rounded-[var(--radius-md)] border border-danger bg-surface px-3 text-sm font-semibold text-danger-fg hover:bg-danger-bg">Remove<span class="sr-only"> {{ $passkey->name }}</span></button>
                    </form>
                </li>
            @empty
                <li class="py-3 text-sm text-ink-secondary">No passkeys yet.</li>
            @endforelse
        </ul>

        <div x-data="passkeyRegister" class="mt-4 max-w-sm">
            <p x-show="!supported" class="text-sm text-ink-secondary">This browser doesn't support passkeys.</p>
            <div x-show="supported" x-cloak>
                <label for="passkey-name" class="block text-sm font-medium text-ink">Passkey name</label>
                <input id="passkey-name" type="text" x-model="name" placeholder="Work laptop" class="{{ \App\Support\Design\ControlClasses::BASE }} mt-2 min-h-12">
                <x-form.button type="button" variant="secondary" class="mt-3" x-on:click="register()" x-bind:disabled="busy">Add a passkey</x-form.button>
                <p x-show="error" x-text="error" role="alert" class="mt-2 text-sm font-medium text-danger-fg"></p>
            </div>
        </div>
    </section>

    {{-- Password --}}
    <section class="mt-6 rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card" aria-labelledby="password-heading">
        <h2 id="password-heading" class="text-lg font-semibold text-ink">Change password</h2>
        <p class="mt-2 text-sm text-ink-secondary">At least 12 characters. Other devices will be signed out.</p>
        <form method="POST" action="{{ route('user-password.update') }}" class="mt-4 max-w-sm" novalidate>
            @csrf
            @method('PUT')
            <x-form.field name="current_password" label="Current password" type="password" autocomplete="current-password" error-bag="updatePassword" required />
            <x-form.field name="password" id="new-password" label="New password" type="password" autocomplete="new-password" error-bag="updatePassword" required />
            <x-form.field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" error-bag="updatePassword" required />
            <x-form.button class="mt-5">Change password</x-form.button>
        </form>
    </section>
</div>
