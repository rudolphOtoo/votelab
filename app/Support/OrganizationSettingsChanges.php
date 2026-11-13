<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Organization;
use Illuminate\Http\UploadedFile;

/**
 * Which validated settings actually change (UpdateOrganizationSettingsAction); OrganizationSettingsWriter saves them. Columns are
 * compared as stored; the brand colour is compared lower-case in settings.brand_color; an uploaded logo is
 * always a change.
 */
final class OrganizationSettingsChanges
{
    public const array COLUMNS = ['name', 'logo_url', 'slug', 'paystack_subaccount_code'];

    /**
     * @param  array<string, mixed>  $data  Validated input.
     * @return array<string, mixed>  field => new value
     */
    public static function of(Organization $organization, array $data): array
    {
        $changed = array_filter(
            array_intersect_key($data, array_flip(self::COLUMNS)),
            static fn(mixed $value, string $field): bool => $organization->getAttribute($field) !== $value,
            ARRAY_FILTER_USE_BOTH,
        );

        if (array_key_exists('brand_color', $data)) {
            $color = is_string($data['brand_color']) && $data['brand_color'] !== ''
                ? strtolower($data['brand_color'])
                : null;

            if ($color !== self::brandColor($organization)) {
                $changed['brand_color'] = $color;
            }
        }

        if (($data['logo'] ?? null) instanceof UploadedFile) {
            $changed['logo'] = $data['logo'];
        }

        return $changed;
    }

    public static function brandColor(Organization $organization): ?string
    {
        $color = ((array) ($organization->settings ?? []))['brand_color'] ?? null;

        return is_string($color) ? strtolower($color) : null;
    }
}
