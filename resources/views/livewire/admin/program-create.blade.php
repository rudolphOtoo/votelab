<div class="max-w-md">
    <p class="text-sm"><a href="{{ route('dashboard') }}" class="text-ink-secondary underline underline-offset-4">Dashboard</a></p>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-ink">New program for {{ $organization->name }}</h1>
    <form wire:submit="save" class="mt-6" novalidate>
        <x-form.field name="title" label="Program title" wire:model.blur="title" required />
        <x-form.field name="slug" label="Link" hint="{{ url('/'.$organization->slug) }}/… — lower-case letters, numbers and hyphens." wire:model="slug" required />
        <x-form.button class="mt-6" wire:loading.attr="disabled">Create program</x-form.button>
    </form>
</div>
