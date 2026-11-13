@php
    $done = collect($checklist->items)->where('passed', true)->count();
    $total = count($checklist->items);
@endphp

<div>
    <x-admin.program-nav :program="$program" current="overview" />

    <x-admin.notice :message="$notice" />
    @error('checklist')<x-admin.notice :message="$message" tone="danger" />@enderror

    {{-- Stats: only what this person may see (ProgramOverview). --}}
    @if ($tiles !== [])
        <section aria-label="Program at a glance" class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            @foreach ($tiles as $tile)
                <x-ui.stat :label="$tile['label']" :value="$tile['value']" :hint="$tile['hint']" :tone="$tile['tone']" wire:key="tile-{{ $tile['key'] }}" />
            @endforeach
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6 lg:order-last">
            {{-- Launch checklist as a guided progress card --}}
            <section class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card" aria-labelledby="checklist-heading">
                <h2 id="checklist-heading" class="text-lg font-semibold text-ink">Launch checklist</h2>
                <x-ui.progress class="mt-3" :label="$done.' of '.$total.' done'" :value="$done" :max="$total" />
                <ul class="mt-4 divide-y divide-hairline">
                    @foreach ($checklist->items as $item)
                        <li class="flex items-start gap-3 py-2.5 text-sm">
                            @if ($item['passed'])
                                <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-success-bg text-success-fg"><x-ui.icon name="check" class="size-3.5" /></span>
                            @else
                                <span class="mt-0.5 size-5 shrink-0 rounded-full border-2 border-strong" aria-hidden="true"></span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="text-ink">{{ $item['label'] }}</span>
                                <span class="sr-only">{{ $item['passed'] ? '(done)' : '(not done)' }}</span>
                                @if ($item['detail'] !== '')<span class="block break-all text-ink-secondary">{{ $item['detail'] }}</span>@endif
                            </span>
                            @if (!$item['passed'] && isset($fixLinks[$item['key']]))
                                <a href="{{ $fixLinks[$item['key']] }}" class="inline-flex min-h-11 shrink-0 items-center -my-2.5 font-semibold text-primary-text underline-offset-4 hover:underline">Fix<span class="sr-only">: {{ $item['label'] }}</span></a>
                            @endif
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
                        <x-admin.confirm-sheet title="Unpublish {{ $program->title }}?" confirm="Unpublish" action="unpublish" danger>Unpublish<x-slot:body>The public pages disappear until you publish again. Votes already cast are kept.</x-slot:body></x-admin.confirm-sheet>
                    @endif
                    @if ($canArchive)
                        <x-admin.confirm-sheet title="Archive {{ $program->title }}?" confirm="Archive" action="archive" danger>Archive<x-slot:body>Archived programs take no new categories. Votes and results are kept.</x-slot:body></x-admin.confirm-sheet>
                    @endif
                </div>
                @if ($canPublish && !$checklist->passes())
                    <p class="mt-2 text-xs text-ink-secondary">Publishing unlocks when every item is done.</p>
                @endif
            </section>

            {{-- Quick actions --}}
            <section class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card" aria-labelledby="actions-heading">
                <h2 id="actions-heading" class="text-lg font-semibold text-ink">Quick actions</h2>
                <ul class="mt-3 grid gap-2">
                    <li><x-ui.button :href="route('dashboard.programs.categories', $program)" variant="secondary" block class="justify-between">Categories & nominees <x-ui.icon name="chevron-right" class="size-4" /></x-ui.button></li>
                    <li><x-ui.button :href="route('dashboard.programs.results', $program)" variant="secondary" block class="justify-between">Results & money <x-ui.icon name="chevron-right" class="size-4" /></x-ui.button></li>
                    <li class="pt-1">
                        <p class="text-sm font-medium text-ink">Public link</p>
                        <p class="mt-1 break-all text-sm text-ink-secondary">{{ $publicUrl }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <x-ui.copy :value="$publicUrl" label="Copy link" copied-label="Link copied" />
                            <x-ui.button :href="'https://wa.me/?text='.rawurlencode('Vote in '.$program->title.': '.$publicUrl)" variant="secondary" size="sm" target="_blank" rel="noopener"><x-ui.icon name="share" class="size-4" /> WhatsApp</x-ui.button>
                        </div>
                    </li>
                </ul>
            </section>
        </div>

        {{-- Details --}}
        <form id="program-details" wire:submit="save" class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card" novalidate>
            <fieldset @disabled(!$canEdit) class="disabled:opacity-80">
                <legend class="text-lg font-semibold text-ink">Details</legend>
                @unless ($canEdit)<p class="mt-1 text-sm text-ink-secondary">You can view these details; an owner or admin can change them.</p>@endunless
                <x-form.field name="title" label="Title" wire:model="title" class="mt-4" />
                <x-form.field name="slug" label="Link" hint="{{ $publicUrl }}" wire:model="slug" />
                <x-form.field name="tagline" label="Tagline" hint="One line under the title on the public page." wire:model="tagline" />
                <div class="mt-4">
                    <label for="program-description" class="block text-sm font-medium text-ink">Description</label>
                    <x-ui.textarea id="program-description" class="mt-2" wire:model="description" rows="4" />
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
    </div>
</div>
