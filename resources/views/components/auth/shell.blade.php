{{-- Centered, mobile-first card for sign-in pages. Announces the session status and the error summary. --}}
@props(['title', 'intro' => null, 'errorBag' => 'default'])

@php $bag = $errors->getBag($errorBag); @endphp

<x-layouts::app :title="$title" bundle="auth" robots="noindex">
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-10">
        <a href="{{ url('/') }}" class="mb-8 inline-flex items-center gap-2 self-start text-base font-semibold tracking-tight text-ink">
            <span class="flex size-8 items-center justify-center rounded-[var(--radius-sm)] bg-primary text-sm text-primary-fg" aria-hidden="true">V</span>
            {{ config('app.name') }}
        </a>

        <div class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-6 shadow-card">
            <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
            @if ($intro)
                <p class="mt-2 text-sm text-ink-secondary">{{ $intro }}</p>
            @endif

            @if (session('status'))
                <p role="status" class="mt-5 flex items-start gap-2 rounded-[var(--radius-md)] bg-success-bg p-3 text-sm text-success-fg"><x-ui.icon name="success" class="mt-0.5 size-4" /> {{ session('status') }}</p>
            @endif

            @if ($bag->any())
                <div role="alert" tabindex="-1" autofocus data-error-summary class="mt-5 rounded-[var(--radius-md)] border border-danger/40 bg-danger-bg p-3 text-sm text-danger-fg outline-none">
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
