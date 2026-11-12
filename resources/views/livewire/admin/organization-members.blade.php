<div>
    <p class="text-sm"><a href="{{ route('dashboard') }}" class="text-ink-secondary underline underline-offset-4">Dashboard</a></p>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-ink">Members of {{ $organization->name }}</h1>

    <div aria-live="polite">
        @if ($notice)
            <p role="status" class="mt-6 rounded-[var(--radius-md)] bg-subtle p-3 text-sm text-ink">{{ $notice }}</p>
        @endif
        @error('members')
            <p role="alert" class="mt-6 rounded-[var(--radius-md)] border border-danger/40 bg-danger/5 p-3 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <section class="mt-8" aria-labelledby="members-heading">
        <h2 id="members-heading" class="text-lg font-semibold text-ink">Members</h2>
        <ul class="mt-3 divide-y divide-hairline rounded-[var(--radius-md)] border border-hairline bg-surface">
            @foreach ($members as $member)
                @php $memberRole = \App\Enums\OrganizationRole::from($member->pivot->role); @endphp
                <li wire:key="member-{{ $member->id }}" class="flex flex-wrap items-center gap-3 p-4">
                    <div class="mr-auto min-w-0">
                        <p class="truncate font-medium text-ink">{{ $member->name }} @if ($member->id === auth()->id()) <span class="text-ink-secondary">(you)</span> @endif</p>
                        <p class="truncate text-sm text-ink-secondary">{{ $member->email }}</p>
                    </div>

                    @if ($canManage)
                        <label for="role-{{ $member->id }}" class="sr-only">Role for {{ $member->name }}</label>
                        <select id="role-{{ $member->id }}" wire:change="changeRole({{ $member->id }}, $event.target.value)" class="h-11 rounded-[var(--radius-md)] border border-hairline bg-surface px-3 text-sm text-ink">
                            @foreach ($roles as $option)
                                <option value="{{ $option->value }}" @selected($option === $memberRole)>{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @if ($member->id !== auth()->id())
                            <button type="button" wire:click="remove({{ $member->id }})" wire:confirm="Remove {{ $member->name }} from {{ $organization->name }}?" class="min-h-11 rounded-[var(--radius-md)] px-3 text-sm text-danger hover:bg-danger/5">Remove</button>
                        @endif
                    @else
                        <span class="text-sm text-ink-secondary">{{ $memberRole->label() }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
        <button type="button" wire:click="leave" wire:confirm="Leave {{ $organization->name }}? You'll need a new invitation to come back." class="mt-3 min-h-11 text-sm text-danger underline underline-offset-4">Leave this organization</button>
    </section>

    @if ($canManage)
        <section class="mt-10" aria-labelledby="invite-heading">
            <h2 id="invite-heading" class="text-lg font-semibold text-ink">Invite someone</h2>
            <form wire:submit="invite" class="mt-3 max-w-md" novalidate>
                <x-form.field name="email" label="Email" type="email" autocomplete="off" inputmode="email" wire:model="email" required />
                <div class="mt-4">
                    <label for="invite-role" class="block text-sm font-medium text-ink">Role</label>
                    <select id="invite-role" wire:model="role" class="mt-2 h-12 w-full rounded-[var(--radius-md)] border border-hairline bg-surface px-3 text-ink" aria-describedby="invite-role-hint">
                        @foreach ($roles as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    <p id="invite-role-hint" class="mt-1 text-xs text-ink-secondary">Inviting an owner asks you to confirm your password first.</p>
                </div>
                <x-form.button class="mt-5" wire:loading.attr="disabled" wire:target="invite">Send invitation</x-form.button>
            </form>

            <h3 class="mt-8 text-sm font-medium text-ink-secondary">Pending invitations</h3>
            @forelse ($invitations as $invitation)
                <div wire:key="invitation-{{ $invitation->id }}" class="mt-3 flex flex-wrap items-center gap-3 rounded-[var(--radius-md)] border border-hairline bg-surface p-4">
                    <div class="mr-auto min-w-0">
                        <p class="truncate font-medium text-ink">{{ $invitation->email }}</p>
                        <p class="text-sm text-ink-secondary">{{ $invitation->role->label() }} · expires {{ $invitation->expires_at->timezone(config('app.timezone'))->format('j M, H:i') }}</p>
                    </div>
                    <button type="button" wire:click="revoke({{ $invitation->id }})" class="min-h-11 rounded-[var(--radius-md)] px-3 text-sm text-danger hover:bg-danger/5">Revoke</button>
                </div>
            @empty
                <p class="mt-3 text-sm text-ink-secondary">No pending invitations.</p>
            @endforelse
        </section>
    @endif
</div>
