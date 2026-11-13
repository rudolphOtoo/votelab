<main class="mx-auto w-full max-w-5xl px-4 py-8 sm:py-12">
    <header>
        <x-ui.badge tone="info" icon="info">Development only</x-ui.badge>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-ink sm:text-4xl">VoteLab design system</h1>
        <p class="mt-2 max-w-2xl text-ink-secondary">Tokens and components for every screen. Each colour pair below is checked for WCAG 2.2 AA in the test suite.</p>
    </header>

    {{-- Tokens --}}
    <section class="mt-10" aria-labelledby="tokens">
        <h2 id="tokens" class="text-2xl font-semibold text-ink">Colour tokens</h2>
        <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
            @foreach ($colors as $name => $hex)
                <li class="rounded-[var(--radius-md)] border border-hairline bg-surface p-2">
                    <span class="block h-10 rounded-[var(--radius-sm)] border border-hairline" style="background: {{ $hex }}"></span>
                    <span class="mt-2 block text-xs font-medium text-ink">--{{ $name }}</span>
                    <span class="block font-mono text-xs text-ink-secondary">{{ $hex }}</span>
                </li>
            @endforeach
        </ul>

        <h3 class="mt-8 text-lg font-semibold text-ink">Contrast (WCAG 2.2 AA)</h3>
        <x-ui.table class="mt-3" caption="Colour pairs and their contrast"
            :columns="['usage' => 'Used for', 'theme' => 'Theme', 'pair' => 'Pair', 'ratio' => 'Ratio', 'needs' => 'Needs', 'result' => 'Result']"
            :numeric="['ratio', 'needs']"
            :rows="array_map(fn ($p) => ['usage' => $p['usage'], 'theme' => $p['theme'], 'pair' => $p['foreground'].' on '.$p['background'], 'ratio' => number_format($p['ratio'], 2).':1', 'needs' => $p['minimum'].':1', 'result' => $p['ratio'] >= $p['minimum'] ? 'Pass' : 'FAIL'], $pairs)" />
    </section>

    {{-- Type --}}
    <section class="mt-12" aria-labelledby="type">
        <h2 id="type" class="text-2xl font-semibold text-ink">Type scale</h2>
        <div class="mt-4 space-y-2">
            @foreach (['text-5xl' => '5xl', 'text-4xl' => '4xl', 'text-3xl' => '3xl', 'text-2xl' => '2xl', 'text-xl' => 'xl', 'text-lg' => 'lg', 'text-base' => 'base', 'text-sm' => 'sm', 'text-xs' => 'xs'] as $class => $size)
                {{-- Full class names so Tailwind's scanner sees each one. --}}
                <div class="flex items-baseline gap-3"><span class="w-10 shrink-0 font-mono text-xs text-ink-tertiary">{{ $size }}</span><p class="{{ $class }} {{ in_array($size, ['5xl', '4xl', '3xl', '2xl', 'xl'], true) ? 'font-semibold tracking-tight' : '' }} text-ink">Vote for Akosua Mensah-Boateng</p></div>
            @endforeach
        </div>
    </section>

    {{-- Spacing, radius, elevation --}}
    <section class="mt-12" aria-labelledby="space">
        <h2 id="space" class="text-2xl font-semibold text-ink">Spacing, radius, elevation</h2>
        <div class="mt-4 flex flex-wrap items-end gap-3">
            @foreach ([1, 2, 3, 4, 6, 8, 12, 16] as $step)
                <div class="text-center"><div class="bg-primary-subtle" style="width: {{ $step * 4 }}px; height: {{ $step * 4 }}px"></div><p class="mt-1 font-mono text-xs text-ink-secondary">{{ $step * 4 }}px</p></div>
            @endforeach
        </div>
        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="h-16 rounded-[var(--radius-sm)] bg-surface shadow-card"><p class="p-2 text-xs text-ink-secondary">radius-sm · card</p></div>
            <div class="h-16 rounded-[var(--radius-md)] bg-surface shadow-raised"><p class="p-2 text-xs text-ink-secondary">radius-md · raised</p></div>
            <div class="h-16 rounded-[var(--radius-lg)] bg-surface shadow-overlay"><p class="p-2 text-xs text-ink-secondary">radius-lg · overlay</p></div>
            <div class="h-16 rounded-full bg-surface shadow-card"><p class="p-2 px-4 text-xs text-ink-secondary">radius-full</p></div>
        </div>
    </section>

    {{-- Buttons --}}
    <section class="mt-12" aria-labelledby="buttons">
        <h2 id="buttons" class="text-2xl font-semibold text-ink">Buttons</h2>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <x-ui.button type="button">Primary</x-ui.button>
            <x-ui.button type="button" variant="secondary">Secondary</x-ui.button>
            <x-ui.button type="button" variant="ghost">Ghost</x-ui.button>
            <x-ui.button type="button" variant="danger">Close voting</x-ui.button>
            <x-ui.button type="button" :loading="true">Securing payment…</x-ui.button>
            <x-ui.button type="button" disabled>Disabled</x-ui.button>
            <x-ui.button type="button" size="sm" variant="secondary">Small</x-ui.button>
            <x-ui.button href="#buttons" variant="secondary">Link button <x-ui.icon name="chevron-right" class="size-4" /></x-ui.button>
        </div>
        <x-ui.button type="button" size="lg" block class="mt-4">Pay <x-ui.money :pesewas="5000" /></x-ui.button>
    </section>

    {{-- Forms --}}
    <section class="mt-12" aria-labelledby="forms">
        <h2 id="forms" class="text-2xl font-semibold text-ink">Form controls</h2>
        @php $errors = new \Illuminate\Support\ViewErrorBag(); $errors->put('default', new \Illuminate\Support\MessageBag(['demo_code' => ['Use 2–8 capital letters and digits, without 0, O, 1 or I.']])); @endphp
        <div class="mt-4 grid gap-6 sm:grid-cols-2">
            <div class="space-y-5">
                <x-ui.field name="demo_email" label="Email for a payment receipt" type="email" optional hint="Paystack may email you a receipt for this payment." autocomplete="email" inputmode="email" wire:model="email" />
                <x-ui.field name="demo_code" label="Nominee code" value="O1" hint="Shown on every nominee card, e.g. AM27." />
                <x-ui.field name="demo_category" label="Category" as="select"><option>Artiste of the Year</option><option>Best Dancer</option></x-ui.field>
                <x-ui.field name="demo_bio" label="Bio" as="textarea" hint="A sentence or two voters will see." />
                <x-ui.field name="demo_disabled" label="Link (locked once voting opens)" value="best-artiste" disabled />
            </div>
            <div class="space-y-4">
                <x-ui.error-summary />
                <fieldset>
                    <legend class="text-sm font-medium text-ink">Voting mode</legend>
                    <x-ui.choice type="radio" name="mode" label="Paid" hint="Voters buy votes with mobile money or card." checked />
                    <x-ui.choice type="radio" name="mode" label="Free" hint="One free vote per voter per day." />
                    <x-ui.choice type="radio" name="mode" label="Both" />
                </fieldset>
                <x-ui.choice label="Keep me signed in" />
                <x-ui.toggle label="Hide results until the reveal" hint="Voting stays open; counts disappear from public pages." :checked="true" />
                <x-ui.stepper label="Number of votes" model="quantity" :min="1" :max="10000" />
                <p class="text-sm text-ink-secondary">Livewire value: <span class="font-semibold tabular-nums text-ink">{{ $quantity }}</span></p>
            </div>
        </div>
    </section>

    {{-- Badges and statuses --}}
    <section class="mt-12" aria-labelledby="badges">
        <h2 id="badges" class="text-2xl font-semibold text-ink">Badges and statuses</h2>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach (['neutral', 'brand', 'success', 'warning', 'danger', 'info'] as $tone)<x-ui.badge :tone="$tone">{{ ucfirst($tone) }}</x-ui.badge>@endforeach
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($statuses as $status)<x-ui.status-pill :status="$status" />@endforeach
        </div>
    </section>

    {{-- Cards, stats, progress, money --}}
    <section class="mt-12" aria-labelledby="cards">
        <h2 id="cards" class="text-2xl font-semibold text-ink">Cards, stats, progress, money</h2>
        <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
            <x-ui.stat label="Votes today" value="1,284" hint="+12% on yesterday" />
            <x-ui.stat label="Total votes" value="48,930" />
            <x-ui.stat label="Revenue" :value="\App\Support\Money::format(4893000)" />
            <x-ui.stat label="Pending payments" value="3" tone="warning" />
            <x-ui.stat label="Refunds waiting" value="1" tone="danger" />
        </div>
        <x-ui.card class="mt-4">
            <x-slot:header><h3 class="font-semibold text-ink">Launch checklist</h3></x-slot:header>
            <x-ui.progress label="4 of 6 done" :value="4" :max="6" />
            <x-slot:footer><x-ui.button type="button" size="sm" variant="secondary">Fix the next item</x-ui.button></x-slot:footer>
        </x-ui.card>
        <p class="mt-4 text-2xl font-semibold text-ink">Total <x-ui.money :pesewas="125000" /></p>
    </section>

    {{-- Nominee photos and avatars --}}
    <section class="mt-12" aria-labelledby="people">
        <h2 id="people" class="text-2xl font-semibold text-ink">Nominee photos and avatars</h2>
        <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <figure><x-ui.nominee-photo name="Akosua Mensah-Boateng" /><figcaption class="mt-2 text-sm font-medium text-ink">Akosua Mensah-Boateng</figcaption></figure>
            <figure><x-ui.skeleton shape="photo" /><figcaption class="mt-2"><x-ui.skeleton :lines="1" /></figcaption></figure>
            <div class="flex items-center gap-3"><x-ui.avatar name="Kwame Asante" /><x-ui.avatar name="Efua Owusu" size="lg" /><x-ui.avatar name="Yaw" size="sm" /></div>
            <x-ui.skeleton :lines="4" />
        </div>
    </section>

    {{-- Navigation --}}
    <section class="mt-12 space-y-6" aria-labelledby="nav">
        <h2 id="nav" class="text-2xl font-semibold text-ink">Navigation</h2>
        <x-ui.breadcrumb :items="['Dashboard' => '#', 'Campus Excellence Awards 2026' => '#', 'Categories' => null]" />
        <x-ui.nav-tabs :items="['Overview' => '#', 'Categories & nominees' => '#', 'Results & money' => '#']" current="Overview" />
        <x-ui.tabs :tabs="['results' => 'Results', 'transactions' => 'Transactions', 'refunds' => 'Refunds']" label="Results sections">
            <x-ui.tab-panel name="results"><p class="text-ink-secondary">Results panel.</p></x-ui.tab-panel>
            <x-ui.tab-panel name="transactions"><p class="text-ink-secondary">Transactions panel.</p></x-ui.tab-panel>
            <x-ui.tab-panel name="refunds"><p class="text-ink-secondary">Refunds panel.</p></x-ui.tab-panel>
        </x-ui.tabs>
        <x-ui.pagination :paginator="$paginator" />
    </section>

    {{-- Table --}}
    <section class="mt-12" aria-labelledby="table">
        <h2 id="table" class="text-2xl font-semibold text-ink">Table (cards under 640 px)</h2>
        <x-ui.table class="mt-4" caption="Example results" :numeric="['votes']"
            :columns="['name' => 'Nominee', 'code' => 'Code', 'votes' => 'Votes']"
            :rows="[['name' => 'Akosua Mensah-Boateng', 'code' => 'AM27', 'votes' => '12,480'], ['name' => 'Kwabena Owusu-Ansah', 'code' => 'KO33', 'votes' => '9,312'], ['name' => 'Efua', 'code' => 'EF22', 'votes' => '402']]" />
    </section>

    {{-- Feedback --}}
    <section class="mt-12 space-y-4" aria-labelledby="feedback">
        <h2 id="feedback" class="text-2xl font-semibold text-ink">Feedback</h2>
        <div class="flex flex-wrap gap-3">
            <x-ui.copy value="AM27" label="Copy code AM27" copied-label="Code copied" />
            <x-ui.copy value="https://votelab.example/org/awards/AM27" label="Copy link" />
            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('toast', { message: 'Saved. Your changes are live.', tone: 'success' })">Show a toast</x-ui.button>
            <x-ui.button type="button" variant="danger" x-on:click="$dispatch('open-sheet', 'close-voting')">Close voting…</x-ui.button>
        </div>
        <x-ui.empty-state title="No programs yet">
            Create your first awards program, add categories and nominees, then publish when the checklist is complete.
            <x-slot:action><x-ui.button type="button">Create a program</x-ui.button></x-slot:action>
        </x-ui.empty-state>
        <x-ui.sheet name="close-voting" title="Close voting in Artiste of the Year?" description="No new votes. Payments already started are still counted if confirmed within 30 minutes and before the reveal. This can't be undone.">
            <x-slot:footer>
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-sheet', 'close-voting')">Cancel</x-ui.button>
                <x-ui.button type="button" variant="danger">Close voting</x-ui.button>
            </x-slot:footer>
        </x-ui.sheet>
    </section>

    {{-- Branding --}}
    <section class="mt-12" aria-labelledby="branding">
        <h2 id="branding" class="text-2xl font-semibold text-ink">Per-organization branding</h2>
        <p class="mt-1 text-ink-secondary">An organization's colour themes its public pages when readable text is possible on it; otherwise VoteLab's brand is used.</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @foreach ($brands as $label => $palette)
                <div class="rounded-[var(--radius-lg)] border border-hairline bg-canvas p-4" style="{{ $palette->declarations() }}">
                    <p class="text-sm font-medium text-ink">{{ $label }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-ui.button type="button" size="sm">Vote now</x-ui.button>
                        <x-ui.badge tone="brand">Winner announced</x-ui.badge>
                        <a href="#branding" class="text-sm font-medium text-primary-text underline underline-offset-4">Brand link</a>
                    </div>
                    <p class="mt-2 font-mono text-xs text-ink-secondary">{{ $palette->brand }} · text {{ $palette->foreground }} · {{ $palette->custom ? 'custom' : 'VoteLab' }}</p>
                </div>
            @endforeach
        </div>
    </section>
</main>
