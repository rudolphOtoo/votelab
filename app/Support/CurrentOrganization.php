<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Session;

/**
 * The organization an organizer is working in, kept in the session and re-checked against their
 * memberships on every read, so a stale or forged session value can never select someone else's.
 */
final class CurrentOrganization
{
    public const string SESSION_KEY = 'dashboard.organization_id';

    public static function for(User $user): ?Organization
    {
        $selected = Session::get(self::SESSION_KEY);

        $organization = is_int($selected) ? $user->organizations()->whereKey($selected)->first() : null;

        return $organization ?? $user->organizations()->orderBy('organizations.name')->first();
    }

    /**
     * @throws AuthorizationException When the user is not a member of that organization.
     */
    public static function switchTo(User $user, int $organizationId): Organization
    {
        $organization = $user->organizations()->whereKey($organizationId)->first();

        if ($organization === null) {
            throw new AuthorizationException('You are not a member of that organization.');
        }

        Session::put(self::SESSION_KEY, $organization->id);

        return $organization;
    }
}
