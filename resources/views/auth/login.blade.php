<x-auth.shell title="Sign in" intro="For event organizers. Voters don't need an account.">
    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <x-form.field name="email" label="Email" type="email" autocomplete="username webauthn" inputmode="email" :value="old('email')" required autofocus />
        <x-form.field name="password" label="Password" type="password" autocomplete="current-password" required />

        <div class="mt-4 flex items-center justify-between gap-4">
            <label class="flex min-h-11 items-center gap-2 text-sm text-ink">
                <input type="checkbox" name="remember" value="1" class="size-4 accent-[var(--accent-brand)]" @checked(old('remember'))>
                Keep me signed in
            </label>
            <a href="{{ route('password.request') }}" class="text-sm font-medium text-ink underline underline-offset-4">Forgot password?</a>
        </div>

        <x-form.button class="mt-6">Sign in</x-form.button>
    </form>

    <div x-data="passkeyLogin" x-cloak x-show="supported" class="mt-4">
        <x-form.button type="button" variant="secondary" x-on:click="signIn()" x-bind:disabled="busy">Sign in with a passkey</x-form.button>
        <p x-show="error" x-text="error" role="alert" class="mt-2 text-sm text-danger"></p>
    </div>

    <x-slot:footer>New organizers join by invitation from their organization's owner.</x-slot:footer>
</x-auth.shell>
