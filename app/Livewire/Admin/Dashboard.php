<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Support\CurrentOrganization;
use App\Support\Organizer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Organizer home: the user's organizations (with a switcher when there are several) and the current
 * organization's programs. Every query goes through the user's own memberships.
 */
#[Layout('layouts::dashboard')]
final class Dashboard extends Component
{
    public function switchOrganization(int $organizationId): void
    {
        CurrentOrganization::switchTo(Organizer::user(), $organizationId);
    }

    public function render(): View
    {
        $user = Organizer::user();
        $organization = CurrentOrganization::for($user);

        return view('livewire.admin.dashboard', [
            'organizations' => $user->organizations()->orderBy('organizations.name')->get(),
            'organization' => $organization,
            'role' => $organization === null ? null : $user->roleIn($organization),
            'programs' => $organization?->awardPrograms()->latest('id')->get() ?? collect(),
        ])->title('Dashboard');
    }
}
