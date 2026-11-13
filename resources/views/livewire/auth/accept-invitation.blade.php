<main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-10">
    <a href="{{ url('/') }}" class="mb-8 inline-flex items-center gap-2 self-start text-base font-semibold tracking-tight text-ink">
        <span class="flex size-8 items-center justify-center rounded-[var(--radius-sm)] bg-primary text-sm text-primary-fg" aria-hidden="true">V</span>
        {{ config('app.name') }}
    </a>

    <div class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-6 shadow-card">
        <h1 class="text-2xl font-semibold tracking-tight text-ink">Join {{ $invitation->organization->name }}</h1>
        <p class="mt-2 text-sm text-ink-secondary">You've been invited as <strong class="text-ink">{{ $invitation->role->label() }}</strong>.</p>

        @if ($problem)
            <div role="alert" class="mt-5 rounded-[var(--radius-md)] border border-danger/40 bg-danger-bg p-3 text-sm text-danger-fg" data-reason="{{ $problem->reason }}">
                {{ $problem->getMessage() }}
            </div>

            @if ($problem->reason === 'sign_in_required')
                <a href="{{ route('login') }}" class="mt-5 flex min-h-12 w-full items-center justify-center rounded-[var(--radius-md)] bg-primary px-4 text-base font-semibold text-primary-fg">Sign in to accept</a>
            @elseif ($problem->reason === 'wrong_account')
                <form method="POST" action="{{ route('logout') }}" class="mt-5">
                    @csrf
                    <x-form.button variant="secondary">Sign out of {{ $signedIn?->email }}</x-form.button>
                </form>
            @elseif ($problem->reason === 'used' || $problem->reason === 'already_member')
                <a href="{{ route('login') }}" class="mt-5 inline-block text-sm font-medium text-ink underline underline-offset-4">Go to sign in</a>
            @endif
        @elseif ($signedIn)
            <p class="mt-5 text-sm text-ink">Signed in as {{ $signedIn->email }}.</p>
            <x-form.button class="mt-5" wire:click="accept" type="button" wire:loading.attr="disabled">Accept and join</x-form.button>
        @else
            <form wire:submit="accept" class="mt-6" novalidate>
                <p class="text-sm text-ink-secondary">Create your organizer account for <strong class="text-ink">{{ $invitation->email }}</strong>.</p>
                @if ($errors->any())
                    <div role="alert" class="mt-4 rounded-[var(--radius-md)] border border-danger/40 bg-danger-bg p-3 text-sm text-danger-fg">Please fix the highlighted fields.</div>
                @endif
                <x-form.field name="name" label="Your name" autocomplete="name" wire:model="name" required class="mt-4" />
                <x-form.field name="password" label="Password" type="password" autocomplete="new-password" hint="At least 12 characters." wire:model="password" required />
                <x-form.field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" wire:model="password_confirmation" required />
                <x-form.button class="mt-6" wire:loading.attr="disabled">Create account and join</x-form.button>
            </form>
        @endif
    </div>
</main>
