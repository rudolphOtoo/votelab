<x-auth.shell title="Confirm it's you" intro="This is a sensitive action. Confirm your password to continue.">
    <form method="POST" action="{{ route('password.confirm.store') }}" novalidate>
        @csrf
        <x-form.field name="password" label="Password" type="password" autocomplete="current-password" required autofocus />
        <x-form.button class="mt-6">Confirm</x-form.button>
    </form>

    <div x-data="passkeyConfirm" x-cloak x-show="supported" class="mt-4">
        <x-form.button type="button" variant="secondary" x-on:click="confirm()" x-bind:disabled="busy">Confirm with a passkey</x-form.button>
        <p x-show="error" x-text="error" role="alert" class="mt-2 text-sm font-medium text-danger-fg"></p>
    </div>
</x-auth.shell>
