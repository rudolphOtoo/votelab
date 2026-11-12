<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Ability;
use App\Enums\CategoryAbility;
use App\Enums\CategoryPhase;
use App\Enums\CategoryStatus;
use App\Enums\ProgramStatus;
use App\Enums\ReportingAbility;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissionMatrix;

/**
 * Category abilities, including status changes (changeStatus) and results visibility.
 */
final class AwardCategoryPolicy
{
    use ChecksPermissionMatrix;

    public function view(User $user, AwardCategory $category): bool
    {
        return $this->granted($user, $category->organization_id, CategoryAbility::CategoryView);
    }

    public function create(User $user, AwardProgram $program): bool
    {
        return (
            $program->status !== ProgramStatus::Archived
            && $this->granted($user, $program->organization_id, CategoryAbility::CategoryCreate)
        );
    }

    public function updateDetails(User $user, AwardCategory $category): bool
    {
        return $this->inPhase($user, $category, CategoryAbility::CategoryUpdateDetails);
    }

    public function updatePricing(User $user, AwardCategory $category): bool
    {
        return $this->inPhase($user, $category, CategoryAbility::CategoryUpdatePricing);
    }

    public function reorder(User $user, AwardCategory $category): bool
    {
        return $this->granted($user, $category->organization_id, CategoryAbility::CategoryReorder);
    }

    /**
     * Open, embargo, unembargo, close or reveal: each move is its own ability in the matrix, and the move
     * must be allowed by CategoryStatus. TransitionCategoryStatusAction (Stage 2c) re-checks it under a lock.
     */
    public function changeStatus(User $user, AwardCategory $category, CategoryStatus $to): bool
    {
        $ability = CategoryAbility::forMove($category->status, $to);

        return (
            $ability !== null
            && $category->status->canTransitionTo($to)
            && $this->granted($user, $category->organization_id, $ability)
        );
    }

    public function delete(User $user, AwardCategory $category): bool
    {
        return $this->inPhase($user, $category, CategoryAbility::CategoryDelete);
    }

    /**
     * Counts in the admin area while they are public anyway (open or revealed).
     */
    public function viewResults(User $user, AwardCategory $category): bool
    {
        return (
            $category->status->isLeaderboardPublic()
            && $this->granted($user, $category->organization_id, ReportingAbility::ResultsView)
        );
    }

    /**
     * Live counts while results are hidden (draft, embargoed, closed): admin area only, never public.
     */
    public function viewHiddenResults(User $user, AwardCategory $category): bool
    {
        return (
            !$category->status->isLeaderboardPublic()
            && $this->granted($user, $category->organization_id, ReportingAbility::ResultsViewHidden)
        );
    }

    private function inPhase(User $user, AwardCategory $category, Ability $ability): bool
    {
        return $this->granted($user, $category->organization_id, $ability, CategoryPhase::of($category->status));
    }
}
