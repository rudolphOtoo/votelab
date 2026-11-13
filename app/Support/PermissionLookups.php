<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Models\AwardCategory;
use App\Models\User;
use Illuminate\Http\Request;
use WeakReference;

/**
 * What the policies look up again and again while one page renders (a list of 30 nominees asks the same
 * questions 30 times): a user's role in an organization, and a category's stored status.
 *
 * - Roles are read once and remembered for the rest of the current request. A new HTTP request (each
 *   Livewire update, each request a test makes) starts empty, and the container flushes this scoped instance
 *   between queue jobs. Every membership write (Organization::addMember, ChangeMemberRoleAction,
 *   RemoveMemberAction) forgets them, so a change made earlier in the same request is always seen.
 * - Category statuses are remembered only while a component renders a list (rememberCategoryStatuses() …
 *   forgetCategoryStatuses(), from Livewire's rendering/rendered hooks); everywhere else each check reads the
 *   stored status afresh, as the policies always did. Saving a category forgets its status.
 * - Not for guards that must read under a row lock: the last-owner checks use User::roleIn directly.
 */
final class PermissionLookups
{
    /** @var array<string, OrganizationRole|null> "userId:organizationId" => role */
    private array $roles = [];

    /** @var array<int, CategoryStatus|null> category id => status */
    private array $statuses = [];

    private bool $rememberStatuses = false;

    /** @var WeakReference<Request>|null */
    private ?WeakReference $request = null;

    public static function role(User $user, int $organizationId): ?OrganizationRole
    {
        $lookups = self::current();
        $key = $user->id . ':' . $organizationId;

        if (!array_key_exists($key, $lookups->roles)) {
            $lookups->roles[$key] = $user->roleIn($organizationId);
        }

        return $lookups->roles[$key];
    }

    public static function categoryStatus(int $awardCategoryId): ?CategoryStatus
    {
        $lookups = self::current();

        if (!$lookups->rememberStatuses) {
            return self::storedStatus($awardCategoryId);
        }

        if (!array_key_exists($awardCategoryId, $lookups->statuses)) {
            $lookups->statuses[$awardCategoryId] = self::storedStatus($awardCategoryId);
        }

        return $lookups->statuses[$awardCategoryId];
    }

    /**
     * Remember category statuses until forgetCategoryStatuses(): for one render of a list.
     */
    public static function rememberCategoryStatuses(): void
    {
        $lookups = self::current();
        $lookups->statuses = [];
        $lookups->rememberStatuses = true;
    }

    public static function forgetCategoryStatuses(): void
    {
        $lookups = self::current();
        $lookups->statuses = [];
        $lookups->rememberStatuses = false;
    }

    public static function forgetMemberships(): void
    {
        self::current()->roles = [];
    }

    public static function forgetCategory(int $awardCategoryId): void
    {
        unset(self::current()->statuses[$awardCategoryId]);
    }

    /**
     * The instance for the current request, emptied when the request object changes.
     */
    private static function current(): self
    {
        $lookups = app(self::class);
        $request = app('request');

        if ($lookups->request?->get() !== $request) {
            $lookups->roles = [];
            $lookups->statuses = [];
            $lookups->rememberStatuses = false;
            $lookups->request = WeakReference::create($request);
        }

        return $lookups;
    }

    private static function storedStatus(int $awardCategoryId): ?CategoryStatus
    {
        $status = AwardCategory::query()->whereKey($awardCategoryId)->value('status');

        return is_string($status) ? CategoryStatus::tryFrom($status) : $status;
    }
}
