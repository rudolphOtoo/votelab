<div>
    <x-ui.breadcrumb class="hidden sm:flex" :items="['Programs' => route('dashboard'), $organization->name => null]" />
    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-ink sm:text-3xl">{{ $organization->name }}</h1>
    <x-ui.nav-tabs class="mt-4" label="Organization sections" :current="$current === 'settings' ? 'Settings' : 'Members'"
        :items="array_filter(['Members' => route('dashboard.members', $organization), 'Settings' => $canSettings ? route('dashboard.members', [$organization, 'tab' => 'settings']) : null])" />

    <x-admin.notice :message="$notice" />
    @error('members')<x-admin.notice :message="$message" tone="danger" />@enderror

    @if ($current === 'settings')
        <div class="mt-6"><livewire:admin.organization-settings :organization="$organization" /></div>
    @else

    <section class="mt-8" aria-labelledby="members-heading">
        <h2 id="members-heading" class="text-lg font-semibold text-ink">Members</h2>
        <ul class="mt-3 divide-y divide-hairline rounded-[var(--radius-lg)] border border-hairline bg-surface shadow-card">
            @foreach ($members as $member)
                @php $memberRole = \App\Enums\OrganizationRole::from($member->pivot->role); @endphp
                <li wire:key="member-{{ $member->id }}" class="flex flex-wrap items-center gap-3 p-4">
                    <x-ui.avatar :name="$member->name" />
                    <div class="mr-auto min-w-0">
                        <p class="truncate font-medium text-ink">{{ $member->name }} @if ($member->id === auth()->id()) <span class="text-ink-secondary">(you)</span> @endif</p>
                        <p class="truncate text-sm text-ink-secondary">{{ $member->email }}</p>
                    </div>

                    @if ($canManage)
                        <label for="role-{{ $member->id }}" class="sr-only">Role for {{ $member->name }}</label>
                        <x-ui.select id="role-{{ $member->id }}" class="w-36" wire:change="changeRole({{ $member->id }}, $event.target.value)">
                            @foreach ($roles as $option)
                                <option value="{{ $option->value }}" @selected($option === $memberRole)>{{ $option->label() }}</option>
                            @endforeach
                        </x-ui.select>
                        @if ($member->id !== auth()->id())
                            <x-admin.confirm-sheet title="Remove {{ $member->name }}?" confirm="Remove" action="remove({{ $member->id }})" danger>Remove<x-slot:body>{{ $member->name }} loses access to {{ $organization->name }} straight away, including every program. They'd need a new invitation to come back.</x-slot:body></x-admin.confirm-sheet>
                        @endif
                    @else
                        <x-ui.badge>{{ $memberRole->label() }}</x-ui.badge>
                    @endif
                </li>
            @endforeach
        </ul>
        <div class="mt-4">
            <x-admin.confirm-sheet title="Leave {{ $organization->name }}?" confirm="Leave" action="leave" danger>Leave this organization<x-slot:body>You lose access to its programs straight away. You'll need a new invitation to come back.</x-slot:body></x-admin.confirm-sheet>
        </div>
    </section>

    @if ($canManage)
        <section class="mt-10" aria-labelledby="invite-heading">
            <h2 id="invite-heading" class="text-lg font-semibold text-ink">Invite someone</h2>
            <form wire:submit="invite" class="mt-3 max-w-md rounded-[var(--radius-lg)] border border-hairline bg-surface p-5 shadow-card" novalidate>
                <x-form.field name="email" label="Email" type="email" autocomplete="off" inputmode="email" wire:model="email" required />
                <div class="mt-4">
                    <label for="invite-role" class="block text-sm font-medium text-ink">Role</label>
                    <x-ui.select id="invite-role" class="mt-2" wire:model="role" aria-describedby="invite-role-hint">
                        @foreach ($roles as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </x-ui.select>
                    <p id="invite-role-hint" class="mt-1 text-sm text-ink-secondary">Inviting an owner asks you to confirm your password first.</p>
                </div>
                <x-form.button class="mt-5" wire:loading.attr="disabled" wire:target="invite">Send invitation</x-form.button>
            </form>

            <h3 class="mt-8 text-sm font-medium text-ink-secondary">Pending invitations</h3>
            @forelse ($invitations as $invitation)
                <div wire:key="invitation-{{ $invitation->id }}" class="mt-3 flex flex-wrap items-center gap-3 rounded-[var(--radius-lg)] border border-hairline bg-surface p-4 shadow-card">
                    <div class="mr-auto min-w-0">
                        <p class="truncate font-medium text-ink">{{ $invitation->email }}</p>
                        <p class="text-sm text-ink-secondary">{{ $invitation->role->label() }} · expires {{ \App\Support\PublicSchedule::format($invitation->expires_at) }}</p>
                    </div>
                    <x-admin.confirm-sheet title="Revoke the invitation for {{ $invitation->email }}?" confirm="Revoke" action="revoke({{ $invitation->id }})" danger>Revoke<x-slot:body>The link in their email stops working. You can invite them again later.</x-slot:body></x-admin.confirm-sheet>
                </div>
            @empty
                <p class="mt-3 text-sm text-ink-secondary">No pending invitations.</p>
            @endforelse
        </section>
    @endif
    @endif
</div>
