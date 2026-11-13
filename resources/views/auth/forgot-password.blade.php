<x-auth.shell title="Reset your password" intro="Enter your email and, if it has an account, we'll send you a reset link.">
    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <x-form.field name="email" label="Email" type="email" autocomplete="username" inputmode="email" :value="old('email')" required autofocus />
        <x-form.button class="mt-6">Email me a reset link</x-form.button>
    </form>

    <x-slot:footer><a href="{{ route('login') }}" class="font-medium text-ink underline underline-offset-4">Back to sign in</a></x-slot:footer>
</x-auth.shell>
