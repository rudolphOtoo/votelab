<div>
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Dashboard</h1>

    @if ($organization === null)
        <p class="mt-6 rounded-[var(--radius-md)] border border-hairline bg-surface p-5 text-sm text-ink-secondary">
            You're not part of an organization yet. Ask an organization owner to invite you.
        </p>
    @else
        @if ($organizations->count() > 1)
            <div class="mt-6">
                <label for="organization-switcher" class="block text-sm font-medium text-ink">Organization</label>
                <select id="organization-switcher" wire:change="switchOrganization(Number($event.target.value))" class="mt-2 h-12 w-full max-w-sm rounded-[var(--radius-md)] border border-hairline bg-surface px-3 text-ink">
                    @foreach ($organizations as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $organization->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <section class="mt-8" aria-labelledby="org-heading">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="org-heading" class="text-lg font-semibold text-ink">{{ $organization->name }}</h2>
                <p class="text-sm text-ink-secondary">
                    Your role: {{ $role?->label() }} ·
                    <a href="{{ route('dashboard.members', $organization) }}" class="font-medium text-ink underline underline-offset-4">Members</a>
                </p>
            </div>

            <div class="mt-6 flex items-center justify-between gap-3">
                <h3 class="text-sm font-medium text-ink-secondary">Programs</h3>
                @can('create', [\App\Models\AwardProgram::class, $organization])
                    <a href="{{ route('dashboard.programs.create', $organization) }}" class="inline-flex min-h-11 items-center rounded-[var(--radius-md)] bg-primary px-3 text-sm font-semibold text-primary-fg">New program</a>
                @endcan
            </div>
            @forelse ($programs as $program)
                <article wire:key="program-{{ $program->id }}" class="mt-3 rounded-[var(--radius-md)] border border-hairline bg-surface p-4">
                    <a href="{{ route('dashboard.programs.show', $program) }}" class="font-medium text-ink underline-offset-4 hover:underline">{{ $program->title }}</a>
                    <p class="mt-1 text-sm text-ink-secondary">{{ $program->status->label() }}</p>
                </article>
            @empty
                <p class="mt-3 text-sm text-ink-secondary">No programs yet.</p>
            @endforelse
        </section>
    @endif
</div>
