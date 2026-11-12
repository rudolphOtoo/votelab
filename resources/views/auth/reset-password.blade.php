<x-auth.shell title="Choose a new password" intro="At least 12 characters. Signing in elsewhere will end once you save.">
    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-form.field name="email" label="Email" type="email" autocomplete="username" :value="old('email', $request->query('email'))" required />
        <x-form.field name="password" label="New password" type="password" autocomplete="new-password" required autofocus />
        <x-form.field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
        <x-form.button class="mt-6">Save password</x-form.button>
    </form>
</x-auth.shell>
