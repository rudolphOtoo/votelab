<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Enums\CategoryStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Support\LeaderboardCache;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A category's nominee grid, also served at a nominee's deep link with that nominee's vote drawer open.
 *
 * Hidden results are enforced here, server-side: vote counts are never public properties and are only
 * loaded (from the leaderboard cache) and rendered while the category's leaderboard is public.
 */
#[Layout('layouts::app')]
final class CategoryShow extends Component
{
    #[Locked]
    public int $categoryId;

    public ?int $selectedNomineeId = null;

    public function mount(
        Organization $organization,
        AwardProgram $awardProgram,
        ?AwardCategory $category = null,
        ?Nominee $nominee = null,
    ): void {
        abort_unless($awardProgram->organization_id === $organization->id && $awardProgram->status->isPublic(), 404);
        abort_if($nominee?->isArchived() === true, 404); // Withdrawn nominees have no public page.

        $category ??= $nominee?->awardCategory()->first();

        abort_if(
            $category === null
            || $category->award_program_id !== $awardProgram->id
            || $category->status === CategoryStatus::Draft,
            404,
        );

        $this->categoryId = $category->id;
        $this->selectedNomineeId = $nominee?->id;
    }

    public function select(int $nomineeId): void
    {
        $belongs = Nominee::query()
            ->active()
            ->whereKey($nomineeId)
            ->where('award_category_id', $this->categoryId)
            ->exists();

        $this->selectedNomineeId = $belongs ? $nomineeId : null;
    }

    #[On('vote-drawer-closed')]
    public function closeDrawer(): void
    {
        $this->selectedNomineeId = null;
    }

    #[On('votes-updated')]
    public function refreshStandings(): void
    {
        // Re-render with the latest cached standings.
    }

    public function render(): View
    {
        $category = AwardCategory::query()->with('awardProgram.organization')->findOrFail($this->categoryId);
        $leaderboardPublic = $category->status->isLeaderboardPublic();

        $nominees = $category
            ->nominees()
            ->active()
            ->select([
                'id',
                'award_category_id',
                'name',
                'stage_name',
                'nominee_code',
                'avatar_url',
                'bio',
                'display_order',
            ])
            ->get();

        // Second layer: LeaderboardCache::standings() already returns null for hidden results.
        $standings = $leaderboardPublic ? LeaderboardCache::standings($category) : null;

        return view('livewire.public.category-show', [
            'category' => $category,
            'program' => $category->awardProgram,
            'organization' => $category->awardProgram->organization,
            'nominees' => $nominees,
            'standings' => $standings,
            'totalVotes' => $standings === null ? 0 : array_sum($standings),
            'acceptsVotes' => $category->status->isVotingAllowed(),
        ])->title("{$category->name} · {$category->awardProgram->title}");
    }
}
