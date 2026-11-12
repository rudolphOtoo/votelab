<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Enums\CategoryStatus;
use App\Livewire\Concerns\AllowsBackForwardCache;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Support\NomineeCode;
use App\Support\PublicSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public landing page for an award program: who runs it, when voting closes, "Find your nominee" and the
 * category grid. Unpublished programs and draft categories are not shown (404 / omitted).
 *
 * The search works without JavaScript too: the form submits ?q= to this page. An exact nominee code goes
 * straight to that nominee; anything else lists up to eight active nominees by name. Never counts, and
 * never ordered by votes (results may be hidden).
 */
#[Layout('layouts::app')]
final class ProgramLanding extends Component
{
    use AllowsBackForwardCache;

    public const int MAX_MATCHES = 8;

    #[Locked]
    public int $programId;

    #[Url(as: 'q', except: '')]
    public string $q = '';

    public function mount(Organization $organization, AwardProgram $awardProgram): void
    {
        abort_unless($awardProgram->organization_id === $organization->id && $awardProgram->status->isPublic(), 404);

        $this->programId = $awardProgram->id;
        $this->find();
    }

    /**
     * Jump to the nominee when the search is exactly their code.
     */
    public function find(): void
    {
        $code = NomineeCode::normalize($this->q);

        if ($code === '') {
            return;
        }

        $nominee = $this->searchable()->where('nominee_code', $code)->first();

        if ($nominee !== null) {
            $program = AwardProgram::query()->with('organization')->findOrFail($this->programId);
            $this->redirectRoute('public.nominee', [$program->organization, $program, $nominee->nominee_code]);
        }
    }

    public function render(): View
    {
        $program = AwardProgram::query()->with('organization')->findOrFail($this->programId);
        $categories = $program
            ->categories()
            ->where('status', '!=', CategoryStatus::Draft)
            ->withCount(['nominees' => static fn($query) => $query->whereNull('archived_at')])
            ->get();

        return view('livewire.public.program-landing', [
            'program' => $program,
            'organization' => $program->organization,
            'categories' => $categories,
            'status' => PublicSchedule::programStatus($program, $categories),
            'matches' => $this->matches(),
            'searched' => mb_strlen(trim($this->q)) >= 2,
        ])
            ->title($program->title)
            ->layoutData([
                'description' => "Vote in {$program->title} by {$program->organization->name}. Find your nominee by name or code and vote with mobile money or card.",
                'canonical' => route('public.program', [$program->organization, $program]),
                'brandOrganization' => $program->organization,
            ]);
    }

    /**
     * @return Collection<int, Nominee>
     */
    private function matches(): Collection
    {
        $term = trim($this->q);

        if (mb_strlen($term) < 2) {
            return new Collection();
        }

        $like = '%' . addcslashes($term, '%_\\') . '%';

        return $this
            ->searchable()
            ->where(static function (Builder $query) use ($like, $term): void {
                $query
                    ->where('name', 'like', $like)
                    ->orWhere('stage_name', 'like', $like)
                    ->orWhere('nominee_code', 'like', addcslashes(NomineeCode::normalize($term), '%_\\') . '%');
            })
            ->with('awardCategory:id,name,slug')
            ->orderBy('name')
            ->limit(self::MAX_MATCHES)
            ->get(['id', 'award_category_id', 'name', 'stage_name', 'nominee_code', 'avatar_url']);
    }

    /**
     * Active nominees in this program's public categories.
     *
     * @return Builder<Nominee>
     */
    private function searchable(): Builder
    {
        return Nominee::query()
            ->active()
            ->where('award_program_id', $this->programId)
            ->whereHas('awardCategory', static fn(Builder $query) => $query->where(
                'status',
                '!=',
                CategoryStatus::Draft,
            ));
    }
}
