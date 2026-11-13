<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\UpdateOrganizationSettingsAction;
use App\Models\Organization;
use App\Support\Organizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Organization settings, the "Settings" tab of the organization page (UI Step 3). Every rule is
 * UpdateOrganizationSettingsAction's: each field's own ability, a recently confirmed password for the link and
 * the payout account (asked for here before calling), owners emailed about those two, everything audited.
 */
final class OrganizationSettings extends Component
{
    #[Locked]
    public int $organizationId;

    public string $name = '';

    public string $logo_url = '';

    public string $slug = '';

    public string $paystack_subaccount_code = '';

    public ?string $notice = null;

    public function mount(Organization $organization): void
    {
        Gate::authorize('viewMembers', $organization);

        $this->organizationId = $organization->id;
        $this->fill([
            'name' => $organization->name,
            'logo_url' => (string) $organization->logo_url,
            'slug' => $organization->slug,
            'paystack_subaccount_code' => (string) $organization->paystack_subaccount_code,
        ]);
    }

    public function saveProfile(UpdateOrganizationSettingsAction $update): void
    {
        $this->apply(
            $update,
            ['name' => trim($this->name), 'logo_url' => trim($this->logo_url) === '' ? null : trim($this->logo_url)],
            'Name and logo saved.',
        );
    }

    public function saveLink(UpdateOrganizationSettingsAction $update): void
    {
        $this->apply($update, ['slug' => trim($this->slug)], 'Public link changed. Owners have been emailed.');
    }

    public function savePayout(UpdateOrganizationSettingsAction $update): void
    {
        $code = trim($this->paystack_subaccount_code);
        $this->apply(
            $update,
            ['paystack_subaccount_code' => $code === '' ? null : $code],
            'Payout account saved. Owners have been emailed.',
        );
    }

    public function render(): View
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        $user = Organizer::user();

        return view('livewire.admin.organization-settings', [
            'organization' => $organization,
            'canProfile' => $user->can('updateProfile', $organization),
            'canSlug' => $user->can('updateSlug', $organization),
            'canPayout' => $user->can('updatePayoutSettings', $organization),
            'confirmUrl' => route('password.confirm'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function apply(UpdateOrganizationSettingsAction $update, array $input, string $done): void
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        $sensitive = array_key_exists('slug', $input) || array_key_exists('paystack_subaccount_code', $input);

        if ($sensitive && !Organizer::passwordRecentlyConfirmed()) {
            session()->put('url.intended', route('dashboard.members', [$organization, 'tab' => 'settings']));
            $this->redirectRoute('password.confirm');

            return;
        }

        $saved = $update->execute($organization, Organizer::user(), $input, passwordConfirmed: $sensitive);
        $this->notice = $done;

        if (array_key_exists('slug', $input)) {
            $this->redirectRoute('dashboard.members', [$saved, 'tab' => 'settings']);
        }
    }
}
