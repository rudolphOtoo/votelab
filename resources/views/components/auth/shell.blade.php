{{-- Centered, mobile-first card for sign-in pages. Announces the session status and the error summary. --}}
@props(['title', 'intro' => null, 'errorBag' => 'default'])

@php $bag = $errors->getBag($errorBag); @endphp

<x-layouts::app :title="$title" bundle="auth">
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-10">
        <a href="{{ url('/') }}" class="mb-8 text-sm font-semibold tracking-tight text-ink">{{ config('app.name') }}</a>

        <div class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-6 shadow-xs">
            <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
            @if ($intro)
                <p class="mt-2 text-sm text-ink-secondary">{{ $intro }}</p>
            @endif

            @if (session('status'))
                <p role="status" class="mt-5 rounded-[var(--radius-md)] bg-subtle p-3 text-sm text-ink">{{ session('status') }}</p>
            @endif

            @if ($bag->any())
                <div role="alert" tabindex="-1" autofocus data-error-summary class="mt-5 rounded-[var(--radius-md)] border border-danger/40 bg-danger/5 p-3 text-sm text-danger outline-none">
                    <p class="font-medium">Please fix the following:</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($bag->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6">
                {{ $slot }}
            </div>
        </div>

        @isset($footer)
            <div class="mt-6 text-center text-sm text-ink-secondary">{{ $footer }}</div>
        @endisset
    </main>
</x-layouts::app>
