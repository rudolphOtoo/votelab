@php
    use App\Enums\CategoryStatus;
    use App\Support\Money;

    $user = auth()->user();
@endphp

<div>
    <x-admin.program-nav :program="$program" current="categories" />

    <x-admin.notice :message="$notice" />
    @error('status')<x-admin.notice :message="$message" tone="danger" />@enderror

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-ink">Categories <span class="font-normal text-ink-secondary">({{ $categories->count() }})</span></h2>
        @if ($editingId === null && $canCreate && $categories->isNotEmpty())
            <x-ui.button type="button" wire:click="create" loading="create"><x-ui.icon name="plus" class="size-5" /> Add category</x-ui.button>
        @endif
    </div>

    @if ($editingId !== null)
        <form wire:submit="save" class="mb-6 rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card" novalidate>
            <h3 class="text-lg font-semibold text-ink">{{ $editingId === 0 ? 'New category' : 'Edit category' }}</h3>
            <div class="grid gap-x-4 md:grid-cols-2">
                <x-form.field name="form.name" id="category-name" label="Name" wire:model="form.name" class="mt-4" />
                <x-form.field name="form.slug" id="category-slug" label="Link" hint="Can't change once voting opens." wire:model="form.slug" class="mt-4" />
            </div>
            <div class="mt-4">
                <label for="category-description" class="block text-sm font-medium text-ink">Description <span class="font-normal text-ink-secondary">(optional)</span></label>
                <x-ui.textarea id="category-description" class="mt-2" wire:model="form.description" rows="3" />
            </div>
            <fieldset class="mt-5 rounded-[var(--radius-md)] border border-hairline p-4">
                <legend class="px-1 text-sm font-semibold text-ink">Voting <span class="font-normal text-ink-secondary">(can only change while in Draft)</span></legend>
                <label for="category-mode" class="block text-sm font-medium text-ink">Mode</label>
                <x-ui.select id="category-mode" class="mt-2" wire:model="form.voting_mode">
                    @foreach ($modes as $mode)<option value="{{ $mode->value }}">{{ $mode->label() }}</option>@endforeach
                </x-ui.select>
                <div class="grid gap-x-4 sm:grid-cols-2">
                    <x-form.field name="form.price" id="category-price" label="Price per vote (GHS)" inputmode="decimal" wire:model="form.price" />
                    <x-form.field name="form.free_vote_limit" id="category-free-limit" label="Free votes per voter per day" type="number" inputmode="numeric" min="1" wire:model="form.free_vote_limit" />
                </div>
            </fieldset>
            <div class="sticky bottom-0 -mx-5 mt-6 flex gap-2 border-t border-hairline bg-surface px-5 py-3 sm:static sm:mx-0 sm:border-0 sm:p-0">
                <x-ui.button type="button" variant="secondary" wire:click="cancel" class="flex-1 sm:flex-none">Cancel</x-ui.button>
                <x-ui.button type="submit" loading="save" class="flex-1 sm:flex-none">Save</x-ui.button>
            </div>
        </form>
    @endif

    @if ($categories->isEmpty())
        <x-ui.empty-state title="No categories yet">
            Categories are what voters choose between, like “Artiste of the Year”. Each can be paid, free or both.
            @if ($canCreate && $editingId === null)
                <x-slot:action><x-ui.button type="button" wire:click="create"><x-ui.icon name="plus" class="size-5" /> Add the first category</x-ui.button></x-slot:action>
            @endif
        </x-ui.empty-state>
    @else
        <ul class="space-y-3" wire:loading.class="opacity-60" wire:target="move,changeStatus,reveal">
            @foreach ($categories as $category)
                <li wire:key="category-{{ $category->id }}" class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-4 shadow-card sm:p-5">
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-ink">{{ $category->name }}</h3>
                                <x-ui.status-pill :status="$category->status" />
                            </div>
                            <p class="mt-1 text-sm text-ink-secondary">
                                {{ $category->voting_mode->label() }}
                                @if ($category->voting_mode->allowsPaidVotes()) · <span class="tabular-nums">{{ Money::format($category->price_per_vote) }}</span> per vote @endif
                                · {{ trans_choice(':count nominee|:count nominees', $category->nominees_count) }}
                            </p>
                        </div>
                        @can('reorder', $category)
                            <div class="flex shrink-0 gap-1">
                                <x-ui.button type="button" variant="ghost" size="sm" wire:click="move({{ $category->id }}, -1)" aria-label="Move {{ $category->name }} up" :disabled="$loop->first"><x-ui.icon name="chevron-down" class="size-5 rotate-180" /></x-ui.button>
                                <x-ui.button type="button" variant="ghost" size="sm" wire:click="move({{ $category->id }}, 1)" aria-label="Move {{ $category->name }} down" :disabled="$loop->last"><x-ui.icon name="chevron-down" class="size-5" /></x-ui.button>
                            </div>
                        @endcan
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-ui.button :href="route('dashboard.programs.nominees', [$program, $category])" size="sm">Nominees <x-ui.icon name="chevron-right" class="size-4" /></x-ui.button>
                        @can('updateDetails', $category)
                            <x-ui.button type="button" variant="secondary" size="sm" wire:click="edit({{ $category->id }})">Edit</x-ui.button>
                        @endcan
                        @if ($user->can('changeStatus', [$category, CategoryStatus::Open]))
                            <x-admin.confirm-sheet :title="$category->status === CategoryStatus::Embargoed ? 'Show results again?' : 'Open voting in '.$category->name.'?'" confirm="Confirm" action="changeStatus({{ $category->id }}, 'open')">
                                {{ $category->status === CategoryStatus::Embargoed ? 'Show results' : 'Open voting' }}
                                <x-slot:body>{{ $category->status === CategoryStatus::Embargoed ? 'Vote counts become public again on the category page and the stage screen.' : 'Voters can start voting in '.$category->name.' straight away.' }}</x-slot:body>
                            </x-admin.confirm-sheet>
                        @endif
                        @if ($user->can('changeStatus', [$category, CategoryStatus::Embargoed]))
                            <x-admin.confirm-sheet title="Hide results in {{ $category->name }}?" confirm="Hide results" action="changeStatus({{ $category->id }}, 'embargoed')">Hide results<x-slot:body>Voting continues; counts disappear from public pages and the stage screen until shown again or revealed.</x-slot:body></x-admin.confirm-sheet>
                        @endif
                        @if ($user->can('changeStatus', [$category, CategoryStatus::Closed]))
                            <x-admin.confirm-sheet title="Close voting in {{ $category->name }}?" confirm="Close voting" action="changeStatus({{ $category->id }}, 'closed')" danger>Close voting<x-slot:body>No new votes. Payments already started are still counted if confirmed within {{ $program->close_grace_minutes }} minutes and before the reveal. This can't be undone.</x-slot:body></x-admin.confirm-sheet>
                        @endif
                        @if ($user->can('changeStatus', [$category, CategoryStatus::Revealed]))
                            <x-admin.confirm-sheet title="Reveal the winner of {{ $category->name }}?" confirm="Reveal" action="reveal({{ $category->id }})" danger id="reveal-{{ $category->id }}">
                                Reveal winner
                                <x-slot:body>Votes are recounted from the ledger, then results go public and the stage screen plays the reveal. This can't be undone.</x-slot:body>
                                @if ($pending[$category->id] > 0)
                                    <x-slot:extra>
                                        <p role="alert" class="mt-4 rounded-[var(--radius-md)] bg-warning-bg p-3 text-sm text-warning-fg"><strong>{{ $pending[$category->id] }}</strong> payment(s) started before the close are still being confirmed.</p>
                                        <label class="mt-3 flex min-h-11 items-start gap-3 text-sm text-ink">
                                            <input type="checkbox" wire:model="revealAnyway" class="mt-0.5 size-5 shrink-0 accent-[var(--brand)]">
                                            <span>Reveal anyway: those payments will be refunded instead of counted. You'll be asked for your password.</span>
                                        </label>
                                    </x-slot:extra>
                                @endif
                            </x-admin.confirm-sheet>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
