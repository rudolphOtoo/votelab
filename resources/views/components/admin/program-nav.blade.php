{{--
  Program header: breadcrumb (from 640 px), title, status and public link, and the section tabs, which
  scroll sideways on phones (no hidden menus). $crumb adds a last breadcrumb step (e.g. a category name).
--}}
@props(['program', 'current', 'crumb' => null])

@php
    use App\Enums\ProgramStatus;

    $tabs = [
        'overview' => ['Overview', route('dashboard.programs.show', $program)],
        'categories' => ['Categories & nominees', route('dashboard.programs.categories', $program)],
        'results' => ['Results & money', route('dashboard.programs.results', $program)],
    ];
    $trail = ['Programs' => route('dashboard'), $program->title => route('dashboard.programs.show', $program)];

    if ($current !== 'overview') {
        $trail[$tabs[$current][0]] = $crumb ? $tabs[$current][1] : null;
    }

    if ($crumb) {
        $trail[$crumb] = null;
    } elseif ($current === 'overview') {
        $trail[$program->title] = null;
    }

    $publicUrl = url("/{$program->organization->slug}/{$program->slug}");
@endphp

<div class="mb-6">
    <x-ui.breadcrumb :items="$trail" class="hidden sm:flex" />
    <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center gap-1 text-sm text-ink-secondary sm:hidden"><x-ui.icon name="chevron-left" class="size-4" /> Programs</a>

    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2">
        <h1 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">{{ $program->title }}</h1>
        <x-ui.badge :tone="match ($program->status) { ProgramStatus::Published => 'success', ProgramStatus::Archived => 'neutral', default => 'warning' }">{{ $program->status->label() }}</x-ui.badge>
        @if ($program->status === ProgramStatus::Published)
            <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-primary-text underline-offset-4 hover:underline">
                View public page <x-ui.icon name="external" class="size-4" /><span class="sr-only">(opens in a new tab)</span>
            </a>
        @endif
    </div>

    <x-ui.nav-tabs class="mt-4" label="Program sections" :current="$tabs[$current][0]" :items="collect($tabs)->mapWithKeys(fn ($tab) => [$tab[0] => $tab[1]])->all()" />
</div>
