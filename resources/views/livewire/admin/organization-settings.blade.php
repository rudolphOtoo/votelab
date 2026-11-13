<div class="space-y-6">
    <x-admin.notice :message="$notice" />
    @error('confirmation')<x-admin.notice :message="$message" tone="danger" />@enderror

    <form wire:submit="saveProfile" class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card" novalidate>
        <fieldset @disabled(!$canProfile)>
            <legend class="text-lg font-semibold text-ink">Name, logo and colour</legend>
            <p class="mt-1 text-sm text-ink-secondary">Shown on every public page, on share images and on the stage screen.</p>
            <x-form.field name="name" id="org-name" label="Organization name" wire:model="name" class="mt-4" />

            {{-- Logo upload --}}
            <div class="mt-5">
                <p class="text-sm font-medium text-ink" id="org-logo-label">Logo</p>
                <div class="mt-2 flex flex-wrap items-center gap-4">
                    <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-[var(--radius-md)] border border-hairline bg-subtle">
                        @if ($logoPreview)
                            <img src="{{ $logoPreview }}" alt="Logo preview" class="max-h-full max-w-full object-contain">
                        @else
                            <span class="text-2xl font-semibold text-ink-tertiary" aria-hidden="true">{{ mb_strtoupper(mb_substr($organization->name, 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <label for="org-logo" class="sr-only">Upload a logo</label>
                        <input id="org-logo" type="file" accept="image/jpeg,image/png,image/webp" wire:model="logo" aria-describedby="org-logo-hint" @error('logo') aria-invalid="true" @enderror
                               class="block w-full text-sm text-ink file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-[var(--radius-md)] file:border file:border-strong file:bg-surface file:px-3 file:text-sm file:font-semibold file:text-ink">
                        <p id="org-logo-hint" class="mt-1 text-sm text-ink-secondary">JPEG, PNG or WebP, up to 2 MB. A square logo with a transparent background works best.</p>
                        <p wire:loading wire:target="logo" class="mt-1 text-sm text-ink-secondary">Uploading…</p>
                        @error('logo')<p class="mt-1 text-sm font-medium text-danger-fg">{{ $message }}</p>@enderror
                        @if ($organization->logo_url && $canProfile)
                            <x-ui.button type="button" variant="ghost" size="sm" class="-ml-3 mt-1" wire:click="removeLogo">Remove logo</x-ui.button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Brand colour --}}
            <div class="mt-5">
                <label for="org-color" class="block text-sm font-medium text-ink">Brand colour <span class="font-normal text-ink-secondary">(optional)</span></label>
                <p id="org-color-hint" class="mt-1 text-sm text-ink-secondary">Used for buttons and links on your public pages. Leave empty for VoteLab's teal.</p>
                <div class="mt-2 flex items-center gap-2">
                    <input type="color" aria-label="Pick a brand colour" value="{{ \App\Support\Design\Contrast::isHex($brand_color) ? strtolower($brand_color) : '#0e6b5e' }}"
                           x-on:input="$wire.set('brand_color', $event.target.value)" class="h-12 w-14 shrink-0 cursor-pointer rounded-[var(--radius-field)] border border-strong bg-surface p-1">
                    <x-ui.input id="org-color" class="max-w-40 font-mono" wire:model.live.debounce.400ms="brand_color" placeholder="#0E6B5E" spellcheck="false" autocomplete="off"
                        aria-describedby="org-color-hint{{ $fallback ? ' org-color-fallback' : '' }}" :invalid="$errors->has('brand_color')" />
                </div>
                @error('brand_color')<p class="mt-1 text-sm font-medium text-danger-fg">{{ $message }}</p>@enderror
                @if ($fallback)
                    <p id="org-color-fallback" role="status" class="mt-2 flex items-start gap-2 rounded-[var(--radius-md)] bg-warning-bg p-3 text-sm text-warning-fg"><x-ui.icon name="alert" class="mt-0.5 size-4" /> {{ $fallback }}</p>
                @endif

                {{-- Live preview: public page (light) and stage screen (dark). --}}
                <div class="mt-3 grid gap-3 sm:grid-cols-2" aria-label="Preview">
                    <div class="rounded-[var(--radius-md)] border border-hairline bg-canvas p-4" style="{{ $preview->declarations() }}">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-secondary">Public page</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="inline-flex min-h-11 items-center rounded-[var(--radius-md)] bg-primary px-4 text-sm font-semibold text-primary-fg">Vote</span>
                            <span class="rounded-full bg-primary-subtle px-2.5 py-0.5 text-xs font-semibold text-primary-text">Winner announced</span>
                            <span class="text-sm font-semibold text-primary-text underline underline-offset-4">See nominees</span>
                        </div>
                    </div>
                    <div class="rounded-[var(--radius-md)] p-4" style="background: #121316">
                        <p class="text-xs font-semibold uppercase tracking-wide" style="color: #a8a29e">Stage screen</p>
                        <p class="mt-2 font-mono text-2xl font-bold" style="color: {{ $stageAccent }}">AM27</p>
                        <p class="text-sm" style="color: #f5f5f4">Akosua Mensah · 1,240 votes</p>
                    </div>
                </div>
            </div>
        </fieldset>
        @if ($canProfile)
            <x-ui.button type="submit" loading="saveProfile" class="mt-5" wire:loading.attr="disabled" wire:target="saveProfile,logo">Save name, logo and colour</x-ui.button>
        @endif
    </form>

    <section class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card" aria-labelledby="org-link-heading">
        <h2 id="org-link-heading" class="text-lg font-semibold text-ink">Public link</h2>
        <p class="mt-1 text-sm text-ink-secondary">The first part of every voting link: {{ url('/') }}/<strong class="text-ink">{{ $organization->slug }}</strong>/…</p>
        <x-form.field name="slug" id="org-slug" label="Link name" hint="Lowercase letters, numbers and single hyphens." wire:model="slug" :disabled="!$canSlug" />
        @if ($canSlug)
            <div class="mt-5">
                <x-admin.confirm-sheet title="Change your public link?" confirm="Change link" action="saveLink" danger>
                    Change link
                    <x-slot:body>Every link you've shared stops working: posters, WhatsApp messages and QR codes that use {{ url('/'.$organization->slug) }} will show “not found”. All owners are emailed. You'll be asked for your password.</x-slot:body>
                </x-admin.confirm-sheet>
            </div>
        @endif
    </section>

    <section class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card" aria-labelledby="org-payout-heading">
        <h2 id="org-payout-heading" class="text-lg font-semibold text-ink">Payout account</h2>
        <p class="mt-1 text-sm text-ink-secondary">Paid votes settle straight to this Paystack subaccount. Find the code (ACCT_…) under Subaccounts in your Paystack dashboard.</p>
        <x-form.field name="paystack_subaccount_code" id="org-payout" label="Paystack subaccount code" placeholder="ACCT_xxxxxxxxxx" autocomplete="off" spellcheck="false" wire:model="paystack_subaccount_code" :disabled="!$canPayout" />
        @if ($canPayout)
            <div class="mt-5">
                <x-admin.confirm-sheet title="Change the payout account?" confirm="Save payout account" action="savePayout" danger>
                    Save payout account
                    <x-slot:body>Money from new paid votes will settle to this account. Check the code carefully: payments to a wrong account are hard to recover. All owners are emailed. You'll be asked for your password.</x-slot:body>
                </x-admin.confirm-sheet>
            </div>
        @endif
    </section>

    @unless ($canProfile || $canSlug || $canPayout)
        <p class="text-sm text-ink-secondary">Only owners can change these settings.</p>
    @endunless
</div>
