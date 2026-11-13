<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\ProgramStatus;
use App\Models\AwardProgram;
use App\Models\User;
use App\Support\Audit;
use App\Support\LaunchChecklist;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Publish (only when the launch checklist passes), unpublish (no category live) or archive a program.
 * Audited.
 */
final readonly class ChangeProgramStatusAction
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function publish(AwardProgram $program, User $actor): AwardProgram
    {
        $program = AwardProgram::query()->findOrFail($program->id);
        Gate::forUser($actor)->authorize('publish', $program);

        $checklist = LaunchChecklist::for($program);

        if (!$checklist->passes()) {
            throw ValidationException::withMessages([
                'checklist' => 'Finish the launch checklist first: ' . implode(', ', $checklist->failing()) . '.',
            ]);
        }

        return $this->move($program, $actor, ProgramStatus::Published, 'program.published');
    }

    /**
     * @throws AuthorizationException
     */
    public function unpublish(AwardProgram $program, User $actor): AwardProgram
    {
        $program = AwardProgram::query()->findOrFail($program->id);
        Gate::forUser($actor)->authorize('unpublish', $program);

        return $this->move($program, $actor, ProgramStatus::Draft, 'program.unpublished');
    }

    /**
     * @throws AuthorizationException
     */
    public function archive(AwardProgram $program, User $actor): AwardProgram
    {
        $program = AwardProgram::query()->findOrFail($program->id);
        Gate::forUser($actor)->authorize('archive', $program);

        return $this->move($program, $actor, ProgramStatus::Archived, 'program.archived');
    }

    private function move(AwardProgram $program, User $actor, ProgramStatus $to, string $action): AwardProgram
    {
        $from = $program->status;
        $program->forceFill(['status' => $to])->save();

        Audit::record($action, $actor, $program, [
            'before' => ['status' => $from->value],
            'after' => ['status' => $to->value],
        ]);

        return $program;
    }
}
