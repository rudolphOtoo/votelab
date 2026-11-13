@php use App\Enums\ProgramStatus; use App\Support\PublicSchedule; @endphp

<div>
    @if ($organization === null)
        <h1 class="text-2xl font-semibold tracking-tight text-ink">Welcome to {{ config('app.name') }}</h1>
        <x-ui.empty-state title="You're not part of an organization yet" class="mt-6">
            Ask the owner of your organization to invite you. The invitation email has a link that signs you in.
        </x-ui.empty-state>
    @else
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm text-ink-secondary">{{ $organization->name }} · {{ $role?->label() }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Programs</h1>
            </div>
            @can('create', [\App\Models\AwardProgram::class, $organization])
                @if ($programs->isNotEmpty())
                    <x-ui.button :href="route('dashboard.programs.create', $organization)"><x-ui.icon name="plus" class="size-5" /> New program</x-ui.button>
                @endif
            @endcan
        </div>

        @if ($organizations->count() > 1)
            {{-- Phones and tablets; from 1024 px the sidebar has the switcher. --}}
            <div class="mt-4 max-w-sm lg:hidden">
                <label for="organization-switcher" class="block text-sm font-medium text-ink">Organization</label>
                <x-ui.select id="organization-switcher" class="mt-2" wire:change="switchOrganization(Number($event.target.value))">
                    @foreach ($organizations as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $organization->id)>{{ $option->name }}</option>
                    @endforeach
                </x-ui.select>
            </div>
        @endif

        @if ($programs->isEmpty())
            {{-- First run: the whole path to a live vote, one step at a time. --}}
            <section class="mt-6 rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card sm:p-8" aria-labelledby="start-heading">
                <h2 id="start-heading" class="text-xl font-semibold text-ink">Set up your first awards</h2>
                <p class="mt-1 max-w-xl text-ink-secondary">Most organizers are ready to share their link in under an hour. Here's the path:</p>
                <ol class="mt-6 grid gap-3 md:grid-cols-5">
                    @foreach ([
                        ['Create the program', 'Name, dates and link.'],
                        ['Add categories', 'Paid, free or both, with a price per vote.'],
                        ['Add nominees', 'Photo, name and a vote code each.'],
                        ['Get the share kit', 'Links, codes and images for WhatsApp.'],
                        ['Publish', 'The checklist makes sure nothing is missing.'],
                    ] as $index => [$step, $detail])
                        <li class="flex gap-3 rounded-[var(--radius-md)] bg-subtle p-4 md:flex-col">
                            <span @class([
                                'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                                'bg-primary text-primary-fg' => $index === 0,
                                'bg-surface text-ink-secondary ring-1 ring-strong' => $index > 0,
                            ]) aria-hidden="true">{{ $index + 1 }}</span>
                            <span>
                                <span class="block font-semibold text-ink">{{ $step }}</span>
                                <span class="block text-sm text-ink-secondary">{{ $detail }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
                @can('create', [\App\Models\AwardProgram::class, $organization])
                    <x-ui.button :href="route('dashboard.programs.create', $organization)" size="lg" class="mt-6">Create your first program</x-ui.button>
                @else
                    <p class="mt-6 text-sm text-ink-secondary">An owner or admin of {{ $organization->name }} can create the program; you'll see it here once they do.</p>
                @endcan
            </section>
        @else
            <ul class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($programs as $program)
                    <li wire:key="program-{{ $program->id }}">
                        <a href="{{ route('dashboard.programs.show', $program) }}" class="group flex h-full flex-col rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card transition-colors hover:border-strong">
                            <x-ui.badge :tone="match ($program->status) { ProgramStatus::Published => 'success', ProgramStatus::Archived => 'neutral', default => 'warning' }" class="self-start">{{ $program->status->label() }}</x-ui.badge>
                            <span class="mt-3 text-lg font-semibold text-ink">{{ $program->title }}</span>
                            <span class="mt-1 text-sm text-ink-secondary">
                                {{ trans_choice(':count category|:count categories', $program->categories_count) }}
                                @if ($program->ends_at) · voting ends {{ PublicSchedule::format($program->ends_at) }} @endif
                            </span>
                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary-text">Open <x-ui.icon name="chevron-right" class="size-4 transition-transform group-hover:translate-x-0.5" /></span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
