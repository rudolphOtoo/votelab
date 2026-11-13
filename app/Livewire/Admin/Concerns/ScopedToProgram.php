<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use App\Models\AwardProgram;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/**
 * For dashboard components that work inside one program: the program id is locked in the component, and
 * every request (not only the first page load) re-loads it and re-checks that the user may view it, so a
 * user removed from the organization mid-session, or from another organization, is refused (403).
 */
trait ScopedToProgram
{
    #[Locked]
    public int $programId;

    protected function bootProgram(AwardProgram $program): void
    {
        Gate::authorize('view', $program);
        $this->programId = $program->id;
    }

    protected function program(): AwardProgram
    {
        $program = AwardProgram::query()->with('organization')->findOrFail($this->programId);
        Gate::authorize('view', $program);

        return $program;
    }
}
