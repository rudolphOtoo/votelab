<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Mail\OrganizationSettingsChangedMail;
use App\Models\Organization;
use App\Models\User;
use App\Rules\OrganizationSlug;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Updates an organization's settings. Each field needs its own ability: name and logo
 * (organization.updateProfile), slug (updateSlug), Paystack subaccount (updatePayoutSettings). Slug and
 * payout changes also need a password confirmed in this session ($passwordConfirmed, from the caller), and
 * on success every owner is emailed (queued) with who changed what and when. Every change is audited with
 * before/after values. Only fields present in $input are touched.
 */
final readonly class UpdateOrganizationSettingsAction
{
    private const array ABILITY_FOR = [
        'name' => 'updateProfile',
        'logo_url' => 'updateProfile',
        'slug' => 'updateSlug',
        'paystack_subaccount_code' => 'updatePayoutSettings',
    ];

    private const array SENSITIVE = ['slug', 'paystack_subaccount_code'];

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        Organization $organization,
        User $actor,
        array $input,
        bool $passwordConfirmed,
    ): Organization {
        $organization = Organization::query()->findOrFail($organization->id);
        $input = array_intersect_key($input, self::ABILITY_FOR);

        $data = Validator::make($input, [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'logo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'slug' => [
                'sometimes',
                'required',
                new OrganizationSlug(),
                Rule::unique('organizations', 'slug')->ignore($organization->id),
            ],
            'paystack_subaccount_code' => ['sometimes', 'nullable', 'regex:/^ACCT_[A-Za-z0-9]+$/'],
        ])->validate();

        $changed = array_filter(
            $data,
            static fn(mixed $value, string $field): bool => $organization->getAttribute($field) !== $value,
            ARRAY_FILTER_USE_BOTH,
        );

        foreach (array_keys($changed) as $field) {
            Gate::forUser($actor)->authorize(self::ABILITY_FOR[$field], $organization);
        }

        $sensitive = array_intersect_key($changed, array_flip(self::SENSITIVE));

        if ($sensitive !== [] && !$passwordConfirmed) {
            throw ValidationException::withMessages([
                'confirmation' => 'Confirm your password to change the URL or payout account.',
            ]);
        }

        if ($changed === []) {
            return $organization;
        }

        $before = array_intersect_key($organization->only(array_keys($changed)), $changed);
        $organization->forceFill($changed)->save();

        Audit::record('organization.settings_changed', $actor, $organization, [
            'before' => $before,
            'after' => $changed,
        ]);

        if ($sensitive !== []) {
            self::notifyOwners($organization, $actor, $before, $sensitive);
        }

        return $organization;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private static function notifyOwners(Organization $organization, User $actor, array $before, array $after): void
    {
        $labels = ['slug' => 'Public URL slug', 'paystack_subaccount_code' => 'Paystack payout account'];
        $changes = [];

        foreach ($after as $field => $value) {
            $changes[] = "{$labels[$field]}: " . ($before[$field] ?? '(none)') . ' → ' . ($value ?? '(none)');
        }

        $owners = $organization->users()->wherePivot('role', 'owner')->get();
        $now = now();

        foreach ($owners as $owner) {
            Mail::to($owner->email)->queue(
                new OrganizationSettingsChangedMail($organization, "{$actor->name} ({$actor->email})", $now, $changes),
            );
        }
    }
}
