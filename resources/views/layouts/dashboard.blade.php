{{--
  Organizer shell: a sidebar from 1024 px (organization switcher, sections, account), a top bar with a menu
  sheet on phones. Program pages add their own breadcrumb and section tabs (x-admin.program-nav). The
  two-factor deadline banner is set by RequireTwoFactorForManagers.
--}}
@php
    use App\Support\CurrentOrganization;

    $user = auth()->user();
    $currentOrganization = $user ? CurrentOrganization::for($user) : null;
    $organizations = $user ? $user->organizations()->orderBy('organizations.name')->get(['organizations.id', 'organizations.name', 'organizations.slug']) : collect();
    $links = array_filter([
        ['Programs', route('dashboard'), request()->routeIs('dashboard', 'dashboard.programs.*'), 'inbox'],
        $currentOrganization ? ['Members', route('dashboard.members', $currentOrganization), request()->routeIs('dashboard.members'), 'user'] : null,
        ['Security', route('dashboard.security'), request()->routeIs('dashboard.security'), 'lock'],
    ]);
@endphp

<x-layouts::app :title="$title ?? null" bundle="admin" robots="noindex">
    <a href="#main" class="sr-only z-[var(--z-toast)] rounded-[var(--radius-md)] bg-surface px-4 py-3 font-semibold text-ink shadow-raised focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Skip to content</a>

    <div class="min-h-screen lg:grid lg:grid-cols-[16rem_minmax(0,1fr)]">
        {{-- Sidebar (desktop) --}}
        <div class="hidden border-r border-hairline bg-surface lg:block">
        <aside class="sticky top-0 flex h-screen flex-col" aria-label="Organizer">
            <div class="px-5 pb-4 pt-5">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 text-base font-semibold tracking-tight text-ink">
                    <span class="flex size-8 items-center justify-center rounded-[var(--radius-sm)] bg-primary text-sm text-primary-fg" aria-hidden="true">V</span>
                    {{ config('app.name') }}
                </a>
            </div>
            <x-admin.organization-switcher :current="$currentOrganization" :organizations="$organizations" class="px-3" />
            <nav class="mt-4 flex-1 px-3" aria-label="Sections">
                <ul class="space-y-1">
                    @foreach ($links as [$label, $href, $active, $icon])
                        <li>
                            <a href="{{ $href }}" @if ($active) aria-current="page" @endif class="flex min-h-11 items-center gap-3 rounded-[var(--radius-md)] px-3 text-sm font-medium text-ink-secondary hover:bg-hover hover:text-ink aria-[current=page]:bg-primary-subtle aria-[current=page]:text-primary-text">
                                <x-ui.icon :name="$icon" class="size-5" /> {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
            @if ($user)
                <div class="border-t border-hairline p-3">
                    <div class="flex items-center gap-3 px-2 py-2">
                        <x-ui.avatar :name="$user->name" size="sm" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-ink">{{ $user->name }}</p>
                            <p class="truncate text-xs text-ink-secondary">{{ $user->email }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-ui.button type="submit" variant="ghost" size="sm" block class="justify-start">Sign out</x-ui.button>
                    </form>
                </div>
            @endif
        </aside>
        </div>

        <div class="min-w-0">
            {{-- Top bar (phones and tablets) --}}
            <header class="sticky top-0 z-[var(--z-sticky)] border-b border-hairline bg-surface lg:hidden">
                <div class="flex items-center gap-3 px-4 py-2">
                    <a href="{{ route('dashboard') }}" class="flex size-9 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-primary text-sm font-semibold text-primary-fg" aria-label="{{ config('app.name') }} home">V</a>
                    <p class="min-w-0 flex-1 truncate text-sm font-semibold text-ink">{{ $currentOrganization?->name ?? config('app.name') }}</p>
                    <x-ui.button type="button" variant="ghost" size="sm" x-data x-on:click="$dispatch('open-sheet', 'organizer-menu')" aria-label="Menu" aria-haspopup="dialog">
                        <x-ui.icon name="menu" />
                    </x-ui.button>
                </div>
            </header>
            <x-ui.sheet name="organizer-menu" title="Menu" side="right">
                <x-admin.organization-switcher :current="$currentOrganization" :organizations="$organizations" />
                <nav class="mt-4" aria-label="Sections">
                    <ul class="space-y-1">
                        @foreach ($links as [$label, $href, $active, $icon])
                            <li><a href="{{ $href }}" @if ($active) aria-current="page" @endif class="flex min-h-12 items-center gap-3 rounded-[var(--radius-md)] px-3 font-medium text-ink hover:bg-hover aria-[current=page]:bg-primary-subtle aria-[current=page]:text-primary-text"><x-ui.icon :name="$icon" class="size-5" /> {{ $label }}</a></li>
                        @endforeach
                    </ul>
                </nav>
                @if ($user)
                    <p class="mt-6 truncate text-sm text-ink-secondary">Signed in as {{ $user->email }}</p>
                    <form method="POST" action="{{ route('logout') }}" class="mt-2">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" block>Sign out</x-ui.button>
                    </form>
                @endif
            </x-ui.sheet>

            @isset($twoFactorDeadline)
                <div role="status" class="border-b border-warning/30 bg-warning-bg">
                    <p class="mx-auto flex max-w-6xl items-start gap-2 px-4 py-3 text-sm text-warning-fg lg:px-8">
                        <x-ui.icon name="alert" class="mt-0.5 size-4" />
                        <span>
                            Owners and admins must turn on two-factor authentication by
                            <strong>{{ $twoFactorDeadline->timezone(\App\Support\PublicSchedule::TIMEZONE)->format('j M Y') }}</strong>.
                            <a href="{{ route('dashboard.security') }}" class="font-semibold underline underline-offset-4">Turn it on now</a>
                        </span>
                    </p>
                </div>
            @endisset

            <main id="main" class="mx-auto w-full max-w-6xl px-4 py-6 lg:px-8 lg:py-8">
                @php
                    $statusMessages = [
                        'two-factor-authentication-enabled' => 'Scan the QR code, then enter a code to finish turning on two-factor.',
                        'two-factor-authentication-confirmed' => 'Two-factor authentication is on. Your other sessions were signed out.',
                        'two-factor-authentication-disabled' => 'Two-factor authentication is off.',
                        'recovery-codes-generated' => 'New recovery codes generated. The old ones no longer work.',
                        'password-updated' => 'Password changed. Your other sessions were signed out.',
                        'passkey-registered' => 'Passkey added.',
                        'passkey-deleted' => 'Passkey removed.',
                    ];
                    $status = session('status');
                @endphp
                @if (is_string($status))
                    <x-admin.notice :message="$statusMessages[$status] ?? $status" />
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts::app>
