<div>
    <x-admin.program-nav :program="$program" current="overview" />

    <div aria-live="polite">
        @if ($notice)<p role="status" class="mb-4 rounded-[var(--radius-md)] bg-subtle p-3 text-sm text-ink">{{ $notice }}</p>@endif
        @error('checklist')<p role="alert" class="mb-4 rounded-[var(--radius-md)] border border-danger/40 bg-danger/5 p-3 text-sm text-danger">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <form wire:submit="save" class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-5" novalidate>
            <fieldset @disabled(!$canEdit) class="disabled:opacity-80">
                <legend class="text-lg font-semibold text-ink">Details</legend>
                <x-form.field name="title" label="Title" wire:model="title" class="mt-4" />
                <x-form.field name="slug" label="Link" hint="{{ $publicUrl }}" wire:model="slug" />
                <x-form.field name="tagline" label="Tagline" wire:model="tagline" />
                <div class="mt-4">
                    <label for="program-description" class="block text-sm font-medium text-ink">Description</label>
                    <textarea id="program-description" wire:model="description" rows="4" class="mt-2 w-full rounded-[var(--radius-md)] border border-hairline bg-surface p-3 text-base text-ink"></textarea>
                </div>
                <div class="grid gap-x-4 sm:grid-cols-2">
                    <x-form.field name="starts_at" label="Voting starts" type="datetime-local" wire:model="starts_at" />
                    <x-form.field name="ends_at" label="Voting ends" type="datetime-local" wire:model="ends_at" />
                </div>
                <x-form.field name="close_grace_minutes" label="Late-payment window (minutes)" type="number" inputmode="numeric" min="{{ $graceMin }}" max="{{ $graceMax }}"
                    hint="After a category closes, payments started before the close are still counted if confirmed within this many minutes and before the reveal ({{ $graceMin }}–{{ $graceMax }}). Locked while a category is closed but not revealed."
                    wire:model="close_grace_minutes" />
            </fieldset>
            @if ($canEdit)
                <div class="sticky bottom-0 -mx-5 mt-6 border-t border-hairline bg-surface px-5 py-3 sm:static sm:mx-0 sm:border-0 sm:p-0">
                    <x-form.button wire:loading.attr="disabled" wire:target="save" class="sm:w-auto">Save program</x-form.button>
                </div>
            @endif
        </form>

        <aside class="space-y-6">
            <section class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-5" aria-labelledby="checklist-heading">
                <h2 id="checklist-heading" class="text-lg font-semibold text-ink">Launch checklist</h2>
                <ul class="mt-3 space-y-2">
                    @foreach ($checklist->items as $item)
                        <li class="flex gap-2 text-sm">
                            <span aria-hidden="true" class="{{ $item['passed'] ? 'text-success' : 'text-danger' }}">{{ $item['passed'] ? '✓' : '✗' }}</span>
                            <span>
                                <span class="text-ink">{{ $item['label'] }}</span>
                                <span class="sr-only">{{ $item['passed'] ? '(done)' : '(not done)' }}</span>
                                @if ($item['detail'] !== '')<span class="block text-xs text-ink-secondary break-all">{{ $item['detail'] }}</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($canPublish)
                        <x-admin.confirm-sheet title="Publish {{ $program->title }}?" confirm="Publish" action="publish" :disabled="!$checklist->passes()">
                            Publish
                            <x-slot:body>Voters will be able to see the program and its open categories at {{ $publicUrl }}.</x-slot:body>
                        </x-admin.confirm-sheet>
                    @endif
                    @if ($canUnpublish)
                        <x-admin.confirm-sheet title="Unpublish?" confirm="Unpublish" action="unpublish">Unpublish<x-slot:body>The public pages will disappear until you publish again.</x-slot:body></x-admin.confirm-sheet>
                    @endif
                    @if ($canArchive)
                        <x-admin.confirm-sheet title="Archive {{ $program->title }}?" confirm="Archive" action="archive" danger>Archive<x-slot:body>Archived programs take no new categories. Votes and results are kept.</x-slot:body></x-admin.confirm-sheet>
                    @endif
                </div>
            </section>

            <section class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-5" aria-labelledby="share-heading" x-data="{ copied: false }">
                <h2 id="share-heading" class="text-lg font-semibold text-ink">Public link</h2>
                <p class="mt-2 break-all text-sm text-ink-secondary">{{ $publicUrl }}</p>
                <button type="button" class="mt-3 min-h-11 rounded-[var(--radius-md)] border border-hairline px-3 text-sm font-medium text-ink" x-on:click="navigator.clipboard.writeText(@js($publicUrl)); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-text="copied ? 'Copied' : 'Copy link'">Copy link</span>
                </button>
            </section>
        </aside>
    </div>
</div>
