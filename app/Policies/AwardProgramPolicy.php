<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CategoryStatus;
use App\Enums\ProgramAbility;
use App\Enums\ProgramStatus;
use App\Models\AwardProgram;
use App\Models\Organization;
use App\Models\User;
use App\Models\VoteRecord;
use App\Policies\Concerns\ChecksPermissionMatrix;

/**
 * Program abilities. Results, money and ledger reading are in ProgramReportingPolicy.
 */
final class AwardProgramPolicy
{
    use ChecksPermissionMatrix;

    public function view(User $user, AwardProgram $program): bool
    {
        return $this->granted($user, $program->organization_id, ProgramAbility::ProgramView);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->granted($user, $organization->id, ProgramAbility::ProgramCreate);
    }

    public function update(User $user, AwardProgram $program): bool
    {
        return $this->granted($user, $program->organization_id, ProgramAbility::ProgramUpdate);
    }

    /**
     * The launch checklist that must pass first is added with it (Stage 2d).
     */
    public function publish(User $user, AwardProgram $program): bool
    {
        return (
            $program->status === ProgramStatus::Draft
            && $this->granted($user, $program->organization_id, ProgramAbility::ProgramPublish)
        );
    }

    /**
     * Not while any category is Live: voters are mid-vote.
     */
    public function unpublish(User $user, AwardProgram $program): bool
    {
        return (
            $program->status === ProgramStatus::Published
            && $this->granted($user, $program->organization_id, ProgramAbility::ProgramPublish)
            && !$program->categories()->whereIn('status', [CategoryStatus::Open, CategoryStatus::Embargoed])->exists()
        );
    }

    public function archive(User $user, AwardProgram $program): bool
    {
        return (
            $program->status !== ProgramStatus::Archived
            && $this->granted($user, $program->organization_id, ProgramAbility::ProgramArchive)
        );
    }

    /**
     * Only a program the ledger has never touched; anything else is archived instead.
     */
    public function delete(User $user, AwardProgram $program): bool
    {
        return (
            $this->granted($user, $program->organization_id, ProgramAbility::ProgramDelete)
            && !$program->voteTransactions()->exists()
            && !VoteRecord::query()->where('award_program_id', $program->id)->exists()
        );
    }
}
