<div>
    <x-admin.program-nav :program="$program" current="categories" />

    <div aria-live="polite">
        @if ($notice)<p role="status" class="mb-4 rounded-[var(--radius-md)] bg-subtle p-3 text-sm text-ink">{{ $notice }}</p>@endif
        @error('status')<p role="alert" class="mb-4 rounded-[var(--radius-md)] border border-danger/40 bg-danger/5 p-3 text-sm text-danger">{{ $message }}</p>@enderror
    </div>

    @if ($editingId !== null)
        <form wire:submit="save" class="mb-6 rounded-[var(--radius-lg)] border border-hairline bg-surface p-5" novalidate>
            <h2 class="text-lg font-semibold text-ink">{{ $editingId === 0 ? 'New category' : 'Edit category' }}</h2>
            <x-form.field name="form.name" id="category-name" label="Name" wire:model="form.name" class="mt-4" />
            <x-form.field name="form.slug" id="category-slug" label="Link" hint="Can't change once voting opens." wire:model="form.slug" />
            <div class="mt-4">
                <label for="category-description" class="block text-sm font-medium text-ink">Description</label>
                <textarea id="category-description" wire:model="form.description" rows="3" class="mt-2 w-full rounded-[var(--radius-md)] border border-hairline bg-surface p-3 text-base text-ink"></textarea>
            </div>
            <fieldset class="mt-4 rounded-[var(--radius-md)] border border-hairline p-4">
                <legend class="px-1 text-sm font-medium text-ink">Voting (Draft only)</legend>
                <label for="category-mode" class="block text-sm text-ink">Mode</label>
                <select id="category-mode" wire:model="form.voting_mode" class="mt-2 h-12 w-full rounded-[var(--radius-md)] border border-hairline bg-surface px-3 text-ink">
                    @foreach ($modes as $mode)<option value="{{ $mode->value }}">{{ $mode->label() }}</option>@endforeach
                </select>
                <div class="grid gap-x-4 sm:grid-cols-2">
                    <x-form.field name="form.price" id="category-price" label="Price per vote (GHS)" inputmode="decimal" wire:model="form.price" />
                    <x-form.field name="form.free_vote_limit" id="category-free-limit" label="Free votes per voter per day" type="number" inputmode="numeric" min="1" wire:model="form.free_vote_limit" />
                </div>
            </fieldset>
            <div class="sticky bottom-0 -mx-5 mt-6 flex gap-2 border-t border-hairline bg-surface px-5 py-3 sm:static sm:mx-0 sm:border-0 sm:p-0">
                <button type="button" wire:click="cancel" class="min-h-12 flex-1 rounded-[var(--radius-md)] border border-hairline px-4 text-sm font-medium text-ink sm:flex-none">Cancel</button>
                <x-form.button class="flex-1 sm:w-auto sm:flex-none" wire:loading.attr="disabled" wire:target="save">Save</x-form.button>
            </div>
        </form>
    @elseif ($canCreate)
        <button type="button" wire:click="create" class="mb-6 min-h-12 w-full rounded-[var(--radius-md)] bg-primary px-4 text-base font-semibold text-primary-fg sm:w-auto">Add category</button>
    @endif

    <ul class="space-y-3">
        @forelse ($categories as $category)
            <li wire:key="category-{{ $category->id }}" class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-4">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="mr-auto min-w-0">
                        <p class="font-medium text-ink">{{ $category->name }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-ink-secondary">
                            <x-public.status-pill :status="$category->status" />
                            {{ $category->voting_mode->label() }} · {{ \App\Support\Money::format($category->price_per_vote) }} · {{ $category->nominees_count }} nominee(s)
                        </p>
                    </div>
                    @can('reorder', $category)
                        <div class="flex gap-1">
                            <button type="button" wire:click="move({{ $category->id }}, -1)" class="size-11 rounded-[var(--radius-md)] border border-hairline text-ink" aria-label="Move {{ $category->name }} up">↑</button>
                            <button type="button" wire:click="move({{ $category->id }}, 1)" class="size-11 rounded-[var(--radius-md)] border border-hairline text-ink" aria-label="Move {{ $category->name }} down">↓</button>
                        </div>
                    @endcan
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('dashboard.programs.nominees', [$program, $category]) }}" class="inline-flex min-h-11 items-center rounded-[var(--radius-md)] border border-hairline px-3 text-sm font-medium text-ink">Nominees</a>
                    @can('updateDetails', $category)
                        <button type="button" wire:click="edit({{ $category->id }})" class="min-h-11 rounded-[var(--radius-md)] border border-hairline px-3 text-sm font-medium text-ink">Edit</button>
                    @endcan
                    @if (auth()->user()->can('changeStatus', [$category, \App\Enums\CategoryStatus::Open]))
                        <x-admin.confirm-sheet title="{{ $category->status === \App\Enums\CategoryStatus::Embargoed ? 'Show results again?' : 'Open voting?' }}" confirm="Confirm" action="changeStatus({{ $category->id }}, 'open')">
                            {{ $category->status === \App\Enums\CategoryStatus::Embargoed ? 'Show results' : 'Open voting' }}
                            <x-slot:body>{{ $category->status === \App\Enums\CategoryStatus::Embargoed ? 'Vote counts become public again.' : 'Voters can start voting in '.$category->name.'.' }}</x-slot:body>
                        </x-admin.confirm-sheet>
                    @endif
                    @if (auth()->user()->can('changeStatus', [$category, \App\Enums\CategoryStatus::Embargoed]))
                        <x-admin.confirm-sheet title="Hide results?" confirm="Hide results" action="changeStatus({{ $category->id }}, 'embargoed')">Hide results<x-slot:body>Voting continues; counts disappear from public pages and the stage screen until shown again or revealed.</x-slot:body></x-admin.confirm-sheet>
                    @endif
                    @if (auth()->user()->can('changeStatus', [$category, \App\Enums\CategoryStatus::Closed]))
                        <x-admin.confirm-sheet title="Close voting in {{ $category->name }}?" confirm="Close voting" action="changeStatus({{ $category->id }}, 'closed')" danger>Close voting<x-slot:body>No new votes. Payments already started are still counted if confirmed within {{ $program->close_grace_minutes }} minutes and before the reveal. This can't be undone.</x-slot:body></x-admin.confirm-sheet>
                    @endif
                    @if (auth()->user()->can('changeStatus', [$category, \App\Enums\CategoryStatus::Revealed]))
                        <div x-data="{ open: false }" class="inline-block">
                            <button type="button" x-on:click="open = true" class="min-h-11 rounded-[var(--radius-md)] bg-primary px-3 text-sm font-semibold text-primary-fg">Reveal winner</button>
                            <template x-teleport="body">
                                <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center" x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
                                    <div role="dialog" aria-modal="true" aria-labelledby="reveal-{{ $category->id }}" x-trap.noscroll="open" class="w-full max-w-md rounded-t-[var(--radius-lg)] bg-surface p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:rounded-[var(--radius-lg)]">
                                        <h2 id="reveal-{{ $category->id }}" class="text-lg font-semibold text-ink">Reveal the winner of {{ $category->name }}?</h2>
                                        <p class="mt-2 text-sm text-ink-secondary">Votes are recounted from the ledger, then results go public and the stage screen plays the reveal. This can't be undone.</p>
                                        @if ($pending[$category->id] > 0)
                                            <p role="alert" class="mt-3 rounded-[var(--radius-md)] bg-warning/10 p-3 text-sm text-ink"><strong>{{ $pending[$category->id] }}</strong> payment(s) started before the close are still being confirmed.</p>
                                            <label class="mt-3 flex min-h-11 items-start gap-2 text-sm text-ink">
                                                <input type="checkbox" wire:model="revealAnyway" class="mt-1 size-4">
                                                <span>Reveal anyway: those payments will be refunded instead of counted. You'll be asked for your password.</span>
                                            </label>
                                        @endif
                                        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                            <button type="button" x-on:click="open = false" class="min-h-12 rounded-[var(--radius-md)] border border-hairline px-4 text-sm font-medium text-ink">Cancel</button>
                                            <button type="button" x-on:click="open = false; $wire.reveal({{ $category->id }})" class="min-h-12 rounded-[var(--radius-md)] bg-primary px-4 text-sm font-semibold text-primary-fg">Reveal</button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    @endif
                </div>
            </li>
        @empty
            <li class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 text-sm text-ink-secondary">No categories yet.</li>
        @endforelse
    </ul>
</div>
