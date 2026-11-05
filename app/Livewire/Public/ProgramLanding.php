<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Enums\CategoryStatus;
use App\Models\AwardProgram;
use App\Models\Organization;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Public landing page for an award program: hero, voting countdown and category grid.
 * Unpublished programs and draft categories are not shown (404 / omitted).
 */
#[Layout('layouts::app')]
final class ProgramLanding extends Component
{
    #[Locked]
    public int $programId;

    public function mount(Organization $organization, AwardProgram $awardProgram): void
    {
        abort_unless($awardProgram->organization_id === $organization->id && $awardProgram->status->isPublic(), 404);

        $this->programId = $awardProgram->id;
    }

    public function render(): View
    {
        $program = AwardProgram::query()->with('organization')->findOrFail($this->programId);

        return view('livewire.public.program-landing', [
            'program' => $program,
            'organization' => $program->organization,
            'categories' => $program
                ->categories()
                ->where('status', '!=', CategoryStatus::Draft)
                ->withCount('nominees')
                ->get(),
        ])->title($program->title);
    }
}
