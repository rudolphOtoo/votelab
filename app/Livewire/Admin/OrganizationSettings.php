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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Organization settings, the "Settings" tab of the organization page (UI Step 3). Every rule is
 * UpdateOrganizationSettingsAction's: each field's own ability, a recently confirmed password for the link and
 * the payout account (asked for here before calling), owners emailed about those two, everything audited.
 */
final class OrganizationSettings extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $organizationId;

    public string $name = '';

    public string $slug = '';

    public string $paystack_subaccount_code = '';

    public string $brand_color = '';

    public ?TemporaryUploadedFile $logo = null;

    public ?string $notice = null;

    public function mount(Organization $organization): void
    {
        Gate::authorize('viewMembers', $organization);

        $this->organizationId = $organization->id;
        $this->fill([
            'name' => $organization->name,
            'slug' => $organization->slug,
            'paystack_subaccount_code' => (string) $organization->paystack_subaccount_code,
            'brand_color' => (string) ($organization->settings['brand_color'] ?? ''),
        ]);
    }

    public function saveProfile(UpdateOrganizationSettingsAction $update): void
    {
        $color = strtolower(trim($this->brand_color));
        $input = ['name' => trim($this->name), 'brand_color' => $color === '' ? null : $color];

        if ($this->logo !== null) {
            $input['logo'] = $this->logo;
        }

        $this->apply($update, $input, 'Name, logo and colour saved.');
        $this->reset('logo');
    }

    public function removeLogo(UpdateOrganizationSettingsAction $update): void
    {
        $this->apply($update, ['logo_url' => null], 'Logo removed.');
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
            'preview' => \App\Support\Design\Contrast::isHex($this->brand_color)
                ? \App\Support\Design\Branding::fromColor(strtolower($this->brand_color))
                : \App\Support\Design\Branding::votelab(),
            'stageAccent' => \App\Support\Design\StageBranding::accentFor($this->brand_color),
            'fallback' => \App\Support\Design\Branding::fallbackReason($this->brand_color),
            'logoPreview' => $this->logo?->isPreviewable() ? $this->logo->temporaryUrl() : $organization->logo_url,
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
