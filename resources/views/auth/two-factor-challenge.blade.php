<x-auth.shell title="Two-factor check" intro="Enter the 6-digit code from your authenticator app.">
    <form method="POST" action="{{ route('two-factor.login.store') }}" novalidate>
        @csrf
        <x-form.field name="code" label="Authentication code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" autofocus />
        <x-form.button class="mt-6">Continue</x-form.button>
    </form>

    <details class="mt-6 border-t border-hairline pt-4">
        <summary class="min-h-11 cursor-pointer text-sm font-medium text-ink">Lost your device? Use a recovery code</summary>
        <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-3" novalidate>
            @csrf
            <x-form.field name="recovery_code" label="Recovery code" type="text" autocomplete="off" />
            <x-form.button variant="secondary" class="mt-4">Use recovery code</x-form.button>
        </form>
    </details>
</x-auth.shell>
