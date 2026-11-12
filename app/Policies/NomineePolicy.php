<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Ability;
use App\Enums\CategoryPhase;
use App\Enums\CategoryStatus;
use App\Enums\NomineeAbility;
use App\Models\AwardCategory;
use App\Models\Nominee;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissionMatrix;

/**
 * Nominee abilities; the phase is the nominee's category's. A nominee with any vote record or
 * transaction is never deleted (archive instead); an archived nominee can't be archived again.
 */
final class NomineePolicy
{
    use ChecksPermissionMatrix;

    public function view(User $user, Nominee $nominee): bool
    {
        return $this->granted($user, $nominee->organization_id, NomineeAbility::NomineeView);
    }

    public function create(User $user, AwardCategory $category): bool
    {
        return $this->granted(
            $user,
            $category->organization_id,
            NomineeAbility::NomineeCreate,
            CategoryPhase::of($category->status),
        );
    }

    public function updateProfile(User $user, Nominee $nominee): bool
    {
        return $this->inPhase($user, $nominee, NomineeAbility::NomineeUpdateProfile);
    }

    public function rename(User $user, Nominee $nominee): bool
    {
        return $this->inPhase($user, $nominee, NomineeAbility::NomineeRename);
    }

    public function reorder(User $user, Nominee $nominee): bool
    {
        return $this->inPhase($user, $nominee, NomineeAbility::NomineeReorder);
    }

    public function changeCode(User $user, Nominee $nominee): bool
    {
        return $this->inPhase($user, $nominee, NomineeAbility::NomineeChangeCode);
    }

    /**
     * Both categories must be Draft and in the same program.
     */
    public function changeCategory(User $user, Nominee $nominee, AwardCategory $target): bool
    {
        return (
            $target->award_program_id === $nominee->award_program_id
            && $target->id !== $nominee->award_category_id
            && $this->inPhase($user, $nominee, NomineeAbility::NomineeChangeCategory)
            && $this->granted(
                $user,
                $target->organization_id,
                NomineeAbility::NomineeChangeCategory,
                CategoryPhase::of($target->status),
            )
        );
    }

    public function archive(User $user, Nominee $nominee): bool
    {
        return !$nominee->isArchived() && $this->inPhase($user, $nominee, NomineeAbility::NomineeArchive);
    }

    public function delete(User $user, Nominee $nominee): bool
    {
        return (
            $this->inPhase($user, $nominee, NomineeAbility::NomineeDelete)
            && !$nominee->voteRecords()->exists()
            && !$nominee->voteTransactions()->exists()
        );
    }

    private function inPhase(User $user, Nominee $nominee, Ability $ability): bool
    {
        // The category's status as stored now, not as a model the caller loaded earlier.
        $status = $nominee->awardCategory()->value('status');
        $status = is_string($status) ? CategoryStatus::tryFrom($status) : $status;
        $phase = $status instanceof CategoryStatus ? CategoryPhase::of($status) : null;

        return $this->granted($user, $nominee->organization_id, $ability, $phase);
    }
}
