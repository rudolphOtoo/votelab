<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Organization;
use Illuminate\Validation\ValidationException;

/**
 * Saves organization settings changes (UpdateOrganizationSettingsAction): columns, an uploaded logo (replacing
 * and deleting any earlier upload), the brand colour in settings, and clears cached share images when anything
 * they show changes (D-131).
 */
final class OrganizationSettingsWriter
{
    private const array SHOWN_ON_SHARE_IMAGES = ['name', 'logo_url', 'logo', 'brand_color'];

    /**
     * Saves the changes; returns [before, after] for the audit trail, as plain values.
     *
     * @param  array<string, mixed>  $changed
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     *
     * @throws ValidationException When an uploaded logo isn't a usable image.
     */
    public static function apply(Organization $organization, array $changed): array
    {
        $before = array_intersect_key($organization->only(OrganizationSettingsChanges::COLUMNS), $changed);
        $after = array_intersect_key($changed, array_flip(OrganizationSettingsChanges::COLUMNS));
        $columns = $after;
        $oldLogo = $organization->logo_path;

        if (array_key_exists('logo_url', $changed)) {
            $columns['logo_path'] = null; // An external URL (or none) replaces any uploaded logo.
        }

        if (array_key_exists('logo', $changed)) {
            $logo = OrganizationLogo::store($changed['logo']);
            $before['logo_url'] = $organization->logo_url;
            $after['logo_url'] = $logo['logo_url'];
            $columns = [...$columns, ...$logo];
        }

        if (array_key_exists('brand_color', $changed)) {
            $before['brand_color'] = OrganizationSettingsChanges::brandColor($organization);
            $after['brand_color'] = $changed['brand_color'];
            $columns['settings'] = [
                ...((array) ($organization->settings ?? [])),
                'brand_color' => $changed['brand_color'],
            ];
        }

        $organization->forceFill($columns)->save();

        if (array_key_exists('logo_path', $columns) && $oldLogo !== $organization->logo_path) {
            OrganizationLogo::delete($oldLogo);
        }

        if (array_intersect_key($changed, array_flip(self::SHOWN_ON_SHARE_IMAGES)) !== []) {
            ShareImages::forgetOrganization($organization->id);
        }

        return [$before, $after];
    }
}
