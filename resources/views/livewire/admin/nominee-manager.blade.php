<div>
    <x-admin.program-nav :program="$program" current="categories" />
    <p class="-mt-2 mb-4 text-sm"><a href="{{ route('dashboard.programs.categories', $program) }}" class="text-ink-secondary underline underline-offset-4">All categories</a> / <span class="text-ink">{{ $category->name }}</span></p>

    <div aria-live="polite">
        @if ($notice)<p role="status" class="mb-4 rounded-[var(--radius-md)] bg-subtle p-3 text-sm text-ink">{{ $notice }}</p>@endif
    </div>

    @if ($editingId !== null)
        <form wire:submit="save" class="mb-6 rounded-[var(--radius-lg)] border border-hairline bg-surface p-5" novalidate>
            <h2 class="text-lg font-semibold text-ink">{{ $editingId === 0 ? 'New nominee' : 'Edit '.$editing?->name }}</h2>
            <div class="mt-4 grid gap-6 md:grid-cols-[minmax(0,18rem)_1fr]">
                <div>
                    <label for="nominee-photo" class="block text-sm font-medium text-ink">Photo</label>
                    <input id="nominee-photo" type="file" accept="image/jpeg,image/png,image/webp" wire:model="form.photo" aria-describedby="nominee-photo-hint" class="mt-2 block w-full text-sm text-ink file:mr-3 file:min-h-11 file:rounded-[var(--radius-md)] file:border file:border-hairline file:bg-surface file:px-3 file:text-sm">
                    <p id="nominee-photo-hint" class="mt-1 text-xs text-ink-secondary">JPEG, PNG or WebP, up to {{ (int) (config('organizations.nominee_photos.max_kilobytes') / 1024) }} MB.</p>
                    @error('form.photo')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                    <div wire:loading wire:target="form.photo" class="mt-2 text-sm text-ink-secondary">Uploading…</div>

                    @if ($cropSource)
                        <div wire:key="crop-{{ md5($cropSource) }}" x-data="cropEditor($wire.entangle('form.crop'), @js($cropSource))" class="mt-4">
                            <p id="crop-help" class="text-xs text-ink-secondary">Drag the photo to frame the face. Use the slider or arrow keys to adjust.</p>
                            <div x-ref="frame" tabindex="0" role="img" aria-label="Photo crop preview (4:5)" aria-describedby="crop-help"
                                 x-on:pointerdown="start($event)" x-on:pointermove="move($event)" x-on:pointerup="end()" x-on:pointercancel="end()"
                                 x-on:keydown.left.prevent="nudge(-0.05, 0)" x-on:keydown.right.prevent="nudge(0.05, 0)"
                                 x-on:keydown.up.prevent="nudge(0, -0.05)" x-on:keydown.down.prevent="nudge(0, 0.05)"
                                 class="relative mt-2 aspect-[4/5] w-full cursor-grab touch-none overflow-hidden rounded-[var(--radius-md)] bg-subtle outline-none ring-focused focus-visible:ring-2 active:cursor-grabbing">
                                <img :src="src" alt="" draggable="false" class="pointer-events-none absolute left-0 top-0 max-w-none select-none" :style="imageStyle()">
                            </div>
                            <label for="crop-zoom" class="mt-3 block text-sm font-medium text-ink">Zoom</label>
                            <input id="crop-zoom" type="range" min="0" max="100" x-model.number="zoom" x-on:input="applyZoom()" class="mt-2 h-11 w-full accent-[var(--accent-brand)]">
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
                        <textarea id="nominee-bio" wire:model="form.bio" rows="4" class="mt-2 w-full rounded-[var(--radius-md)] border border-hairline bg-surface p-3 text-base text-ink"></textarea>
                        @error('bio')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                    @error('nominee_code')<p role="alert" class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                    @error('name')<p role="alert" class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="sticky bottom-0 -mx-5 mt-6 flex gap-2 border-t border-hairline bg-surface px-5 py-3 sm:static sm:mx-0 sm:border-0 sm:p-0">
                <button type="button" wire:click="$set('editingId', null)" class="min-h-12 flex-1 rounded-[var(--radius-md)] border border-hairline px-4 text-sm font-medium text-ink sm:flex-none">Cancel</button>
                <x-form.button class="flex-1 sm:w-auto sm:flex-none" wire:loading.attr="disabled" wire:target="save,form.photo">Save</x-form.button>
            </div>
        </form>
    @elseif ($canCreate)
        <button type="button" wire:click="open" class="mb-6 min-h-12 w-full rounded-[var(--radius-md)] bg-primary px-4 text-base font-semibold text-primary-fg sm:w-auto">Add nominee</button>
    @endif

    <ul class="grid gap-3 sm:grid-cols-2">
        @forelse ($nominees as $nominee)
            <li wire:key="nominee-{{ $nominee->id }}" x-data="{ share: false }" class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-4 {{ $nominee->isArchived() ? 'opacity-70' : '' }}">
                <div class="flex gap-3">
                    @if ($nominee->avatar_url)
                        <img src="{{ $nominee->avatar_url }}" alt="" class="h-20 w-16 shrink-0 rounded-[var(--radius-sm)] object-cover">
                    @else
                        <div class="flex h-20 w-16 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-subtle text-xs text-ink-secondary">No photo</div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-ink">{{ $nominee->displayName() }}</p>
                        <p class="text-sm text-ink-secondary">Code <span class="font-mono font-semibold text-ink">{{ $nominee->nominee_code }}</span>@if ($nominee->isArchived()) · <span class="font-medium text-warning">Archived</span>@endif</p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if (!$nominee->isArchived() && (auth()->user()->can('updateProfile', $nominee) || auth()->user()->can('rename', $nominee)))
                        <button type="button" wire:click="open({{ $nominee->id }})" class="min-h-11 rounded-[var(--radius-md)] border border-hairline px-3 text-sm font-medium text-ink">Edit</button>
                    @endif
                    @can('reorder', $nominee)
                        <button type="button" wire:click="move({{ $nominee->id }}, -1)" class="size-11 rounded-[var(--radius-md)] border border-hairline text-ink" aria-label="Move {{ $nominee->displayName() }} up">↑</button>
                        <button type="button" wire:click="move({{ $nominee->id }}, 1)" class="size-11 rounded-[var(--radius-md)] border border-hairline text-ink" aria-label="Move {{ $nominee->displayName() }} down">↓</button>
                    @endcan
                    @if (!$nominee->isArchived())
                        <button type="button" x-on:click="share = !share" x-bind:aria-expanded="share.toString()" class="min-h-11 rounded-[var(--radius-md)] border border-hairline px-3 text-sm font-medium text-ink">Share kit</button>
                    @endif
                    @can('archive', $nominee)
                        <x-admin.confirm-sheet title="Archive {{ $nominee->displayName() }}?" confirm="Archive" action="archive({{ $nominee->id }})" danger>Archive<x-slot:body>They disappear from public pages and take no new votes. Votes already cast stay in the results. This can't be undone here.</x-slot:body></x-admin.confirm-sheet>
                    @endcan
                </div>

                @if (!$nominee->isArchived())
                    @php $share = $shares[$nominee->id] ?? null; @endphp
                    <div x-show="share" x-cloak class="mt-4 rounded-[var(--radius-md)] bg-subtle p-3 text-sm" x-data="{ copied: '' }">
                        @if ($share)
                            <p class="font-medium text-ink">Share kit</p>
                            <p class="mt-2 break-all text-ink-secondary">{{ $share['url'] }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" class="min-h-11 rounded-[var(--radius-md)] border border-hairline bg-surface px-3 font-medium text-ink" x-on:click="navigator.clipboard.writeText(@js($share['url'])); copied = 'link'"><span x-text="copied === 'link' ? 'Link copied' : 'Copy link'">Copy link</span></button>
                                <button type="button" class="min-h-11 rounded-[var(--radius-md)] border border-hairline bg-surface px-3 font-medium text-ink" x-on:click="navigator.clipboard.writeText(@js($share['code'])); copied = 'code'"><span x-text="copied === 'code' ? 'Code copied' : 'Copy code {{ $share['code'] }}'">Copy code</span></button>
                                <a href="{{ $share['whatsapp'] }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-[var(--radius-md)] bg-[#25D366] px-3 font-semibold text-[#062a14]">Share on WhatsApp</a>
                            </div>
                            <p class="mt-3 text-xs text-ink-secondary">Images (no vote counts):</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <a href="{{ $share['images']['story'] }}?download=1" class="inline-flex min-h-11 items-center rounded-[var(--radius-md)] border border-hairline bg-surface px-3 font-medium text-ink">WhatsApp Status (1080×1920)</a>
                                <a href="{{ $share['images']['square'] }}?download=1" class="inline-flex min-h-11 items-center rounded-[var(--radius-md)] border border-hairline bg-surface px-3 font-medium text-ink">Square (1080×1080)</a>
                            </div>
                        @else
                            <p class="text-ink-secondary">The share kit is available once the program is published and this category is open.</p>
                        @endif
                    </div>
                @endif
            </li>
        @empty
            <li class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 text-sm text-ink-secondary">No nominees yet.</li>
        @endforelse
    </ul>
</div>
