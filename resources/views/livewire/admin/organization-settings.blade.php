<div class="space-y-6">
    <x-admin.notice :message="$notice" />
    @error('confirmation')<x-admin.notice :message="$message" tone="danger" />@enderror

    <form wire:submit="saveProfile" class="rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card" novalidate>
        <fieldset @disabled(!$canProfile)>
            <legend class="text-lg font-semibold text-ink">Name and logo</legend>
            <p class="mt-1 text-sm text-ink-secondary">Shown at the top of every public page and on share images.</p>
            <x-form.field name="name" id="org-name" label="Organization name" wire:model="name" class="mt-4" />
            <x-form.field name="logo_url" id="org-logo" label="Logo URL" type="url" inputmode="url" hint="A square image works best. Leave empty to show your initial." wire:model="logo_url" />
        </fieldset>
        @if ($canProfile)
            <x-ui.button type="submit" loading="saveProfile" class="mt-5">Save name and logo</x-ui.button>
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
