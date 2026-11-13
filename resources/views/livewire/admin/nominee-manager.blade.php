<div>
    <x-admin.program-nav :program="$program" current="categories" :crumb="$category->name" />

    <x-admin.notice :message="$notice" />

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('dashboard.programs.categories', $program) }}" class="inline-flex min-h-11 items-center gap-1 text-sm text-ink-secondary sm:hidden"><x-ui.icon name="chevron-left" class="size-4" /> All categories</a>
            <h2 class="text-lg font-semibold text-ink">{{ $category->name }} <span class="font-normal text-ink-secondary">· {{ trans_choice(':count nominee|:count nominees', $nominees->count()) }}</span></h2>
        </div>
        @if ($editingId === null && $canCreate && $nominees->isNotEmpty())
            <x-ui.button type="button" wire:click="open" loading="open"><x-ui.icon name="plus" class="size-5" /> Add nominee</x-ui.button>
        @endif
    </div>

    @if ($editingId !== null)
        <form wire:submit="save" class="mb-6 rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card" novalidate>
            <h3 class="text-lg font-semibold text-ink">{{ $editingId === 0 ? 'New nominee' : 'Edit '.$editing?->name }}</h3>
            <div class="mt-4 grid gap-6 md:grid-cols-[minmax(0,18rem)_1fr]">
                <div>
                    <label for="nominee-photo" class="block text-sm font-medium text-ink">Photo</label>
                    <input id="nominee-photo" type="file" accept="image/jpeg,image/png,image/webp" wire:model="form.photo" aria-describedby="nominee-photo-hint" class="mt-2 block w-full text-sm text-ink file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-[var(--radius-md)] file:border file:border-strong file:bg-surface file:px-3 file:text-sm file:font-semibold file:text-ink">
                    <p id="nominee-photo-hint" class="mt-1 text-sm text-ink-secondary">JPEG, PNG or WebP, up to {{ (int) (config('organizations.nominee_photos.max_kilobytes') / 1024) }} MB.</p>
                    @error('form.photo')<p class="mt-2 text-sm font-medium text-danger-fg">{{ $message }}</p>@enderror
                    <div wire:loading wire:target="form.photo" class="mt-3"><x-ui.skeleton shape="photo" /><p class="mt-2 text-sm text-ink-secondary">Uploading…</p></div>

                    @if ($cropSource)
                        <div wire:key="crop-{{ md5($cropSource) }}" x-data="cropEditor($wire.entangle('form.crop'), @js($cropSource))" class="mt-4">
                            <p id="crop-help" class="text-sm text-ink-secondary">Drag the photo to frame the face. Use the slider or arrow keys to adjust.</p>
                            <div x-ref="frame" tabindex="0" role="img" aria-label="Photo crop preview (4:5)" aria-describedby="crop-help"
                                 x-on:pointerdown="start($event)" x-on:pointermove="move($event)" x-on:pointerup="end()" x-on:pointercancel="end()"
                                 x-on:keydown.left.prevent="nudge(-0.05, 0)" x-on:keydown.right.prevent="nudge(0.05, 0)"
                                 x-on:keydown.up.prevent="nudge(0, -0.05)" x-on:keydown.down.prevent="nudge(0, 0.05)"
                                 class="relative mt-2 aspect-[4/5] w-full cursor-grab touch-none overflow-hidden rounded-[var(--radius-md)] bg-subtle outline-none ring-focused focus-visible:ring-2 active:cursor-grabbing">
                                <img :src="src" alt="" draggable="false" class="pointer-events-none absolute left-0 top-0 max-w-none select-none" :style="imageStyle()">
                            </div>
                            <label for="crop-zoom" class="mt-3 block text-sm font-medium text-ink">Zoom</label>
                            <input id="crop-zoom" type="range" min="0" max="100" x-model.number="zoom" x-on:input="applyZoom()" class="mt-2 h-11 w-full accent-[var(--brand)]">
                        </div>
                    @endif
                </div>

                <div>
                    @php $locked = fn(string $ability) => $editing !== null && auth()->user()->cannot($ability, $editing); @endphp
                    <x-form.field name="form.name" id="nominee-name" label="Name" wire:model="form.name" :disabled="$locked('rename')" />
                    <x-form.field name="form.stage_name" id="nominee-stage-name" label="Stage name (optional)" wire:model="form.stage_name" :disabled="$locked('rename')" />
                    <x-form.field name="form.nominee_code" id="nominee-code" label="Vote code" hint="Leave blank to generate one. Capital letters and digits, no 0, O, 1 or I." wire:model="form.nominee_code" :disabled="$locked('changeCode')" />
                    <div class="mt-4">
                        <label for="nominee-bio" class="block text-sm font-medium text-ink">Bio</label>
                        <x-ui.textarea id="nominee-bio" class="mt-2" wire:model="form.bio" rows="4" />
                        @error('bio')<p class="mt-2 text-sm font-medium text-danger-fg">{{ $message }}</p>@enderror
                    </div>
                    @error('nominee_code')<p role="alert" class="mt-2 text-sm font-medium text-danger-fg">{{ $message }}</p>@enderror
                    @error('name')<p role="alert" class="mt-2 text-sm font-medium text-danger-fg">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="sticky bottom-0 -mx-5 mt-6 flex gap-2 border-t border-hairline bg-surface px-5 py-3 sm:static sm:mx-0 sm:border-0 sm:p-0">
                <x-ui.button type="button" variant="secondary" wire:click="$set('editingId', null)" class="flex-1 sm:flex-none">Cancel</x-ui.button>
                <x-ui.button type="submit" class="flex-1 sm:flex-none" wire:loading.attr="disabled" wire:target="save,form.photo">Save</x-ui.button>
            </div>
        </form>
    @endif

    @if ($nominees->isEmpty())
        <x-ui.empty-state title="No nominees in {{ $category->name }} yet">
            Add each nominee with a photo; VoteLab gives them a vote code voters can type or share.
            @if ($canCreate && $editingId === null)
                <x-slot:action><x-ui.button type="button" wire:click="open"><x-ui.icon name="plus" class="size-5" /> Add the first nominee</x-ui.button></x-slot:action>
            @endif
        </x-ui.empty-state>
    @else
        <ul class="grid gap-3 md:grid-cols-2" wire:loading.class="opacity-60" wire:target="move,archive">
            @foreach ($nominees as $nominee)
                <li wire:key="nominee-{{ $nominee->id }}" x-data="{ share: false }" class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-4 shadow-card {{ $nominee->isArchived() ? 'opacity-75' : '' }}">
                    <div class="flex gap-3">
                        <div class="w-16 shrink-0">
                            <x-ui.nominee-photo :src="\App\Support\NomineePortrait::smallest($nominee)" :name="$nominee->displayName()" alt="" :width="64" class="rounded-[var(--radius-sm)] text-xl" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold text-ink">{{ $nominee->displayName() }}</h3>
                            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-ink-secondary">
                                <span>Code <span class="rounded-[var(--radius-sm)] bg-subtle px-1.5 py-0.5 font-mono font-semibold text-ink">{{ $nominee->nominee_code }}</span></span>
                                @if ($nominee->isArchived())<x-ui.badge tone="warning">Archived</x-ui.badge>@endif
                                @if (!$nominee->avatar_url && !$nominee->isArchived())<x-ui.badge tone="warning">No photo</x-ui.badge>@endif
                            </p>
                        </div>
                        @can('reorder', $nominee)
                            <div class="flex shrink-0 flex-col gap-1 sm:flex-row">
                                <x-ui.button type="button" variant="ghost" size="sm" wire:click="move({{ $nominee->id }}, -1)" aria-label="Move {{ $nominee->displayName() }} up" :disabled="$loop->first"><x-ui.icon name="chevron-down" class="size-5 rotate-180" /></x-ui.button>
                                <x-ui.button type="button" variant="ghost" size="sm" wire:click="move({{ $nominee->id }}, 1)" aria-label="Move {{ $nominee->displayName() }} down" :disabled="$loop->last"><x-ui.icon name="chevron-down" class="size-5" /></x-ui.button>
                            </div>
                        @endcan
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if (!$nominee->isArchived() && (auth()->user()->can('updateProfile', $nominee) || auth()->user()->can('rename', $nominee)))
                            <x-ui.button type="button" variant="secondary" size="sm" wire:click="open({{ $nominee->id }})">Edit</x-ui.button>
                        @endif
                        @if (!$nominee->isArchived())
                            <x-ui.button type="button" variant="secondary" size="sm" x-on:click="share = !share" x-bind:aria-expanded="share.toString()" aria-controls="share-{{ $nominee->id }}"><x-ui.icon name="share" class="size-4" /> Share kit</x-ui.button>
                        @endif
                        @can('archive', $nominee)
                            <x-admin.confirm-sheet title="Archive {{ $nominee->displayName() }}?" confirm="Archive" action="archive({{ $nominee->id }})" danger>Archive<x-slot:body>They disappear from public pages and take no new votes. Votes already cast stay in the results. This can't be undone here.</x-slot:body></x-admin.confirm-sheet>
                        @endcan
                    </div>

                    @if (!$nominee->isArchived())
                        @php $share = $shares[$nominee->id] ?? null; @endphp
                        <div id="share-{{ $nominee->id }}" x-show="share" x-cloak class="mt-4 rounded-[var(--radius-md)] bg-subtle p-4 text-sm">
                            @if ($share)
                                <p class="font-semibold text-ink">Share kit</p>
                                <p class="mt-1 break-all text-ink-secondary">{{ $share['url'] }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <x-ui.copy :value="$share['url']" label="Copy link" copied-label="Link copied" />
                                    <x-ui.copy :value="$share['code']" :label="'Copy code '.$share['code']" copied-label="Code copied" />
                                    <x-ui.button :href="$share['whatsapp']" size="sm" target="_blank" rel="noopener"><x-ui.icon name="share" class="size-4" /> Share on WhatsApp</x-ui.button>
                                </div>
                                <p class="mt-4 text-ink-secondary">Images (never show vote counts):</p>
                                <div class="mt-2 grid grid-cols-3 gap-3">
                                    @foreach ([['story', 'WhatsApp Status', '1080×1920', 'aspect-[9/16]'], ['square', 'Square post', '1080×1080', 'aspect-square'], ['og', 'Link preview', '1200×630', 'aspect-[40/21]']] as [$format, $label, $size, $ratio])
                                        <figure class="flex flex-col">
                                            <img src="{{ $share['images'][$format] }}" alt="{{ $label }} image for {{ $nominee->displayName() }}" loading="lazy" decoding="async" class="{{ $ratio }} w-full rounded-[var(--radius-sm)] bg-surface object-cover shadow-card">
                                            <figcaption class="mt-1 text-xs text-ink-secondary">{{ $label }} · {{ $size }}</figcaption>
                                            <x-ui.button :href="$share['images'][$format].'?download=1'" variant="secondary" size="sm" class="mt-2" aria-label="Download {{ $label }} image ({{ $size }})"><x-ui.icon name="download" class="size-4" /><span class="hidden sm:inline">Download</span></x-ui.button>
                                        </figure>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-ink-secondary">The share kit is available once the program is published and this category is open.</p>
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
