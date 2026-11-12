<?php

declare(strict_types=1);

namespace App\Livewire\Stage;

use App\Models\AwardProgram;
use App\Models\Organization;
use App\Services\Security\VoteFingerprintService;
use App\Support\StageScene;
use App\Support\Throttle;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The projector screen at /stage/{organization}/{program} (A.8). No login. Its Livewire state is only the
 * program id and an optional pinned category slug; everything shown is built per request by StageScene,
 * which carries results only once they are public. Polls every 5 s; a throttled poll keeps the last good
 * screen (skipRender), and the page's script suppresses Livewire's error dialog so a failed poll never
 * shows an error to the audience.
 */
#[Layout('layouts::app', ['theme' => 'stage'])]
final class StageLeaderboard extends Component
{
    #[Locked]
    public int $programId;

    #[Url(as: 'category')]
    public string $pinned = '';

    public function mount(Organization $organization, AwardProgram $awardProgram): void
    {
        abort_unless($awardProgram->organization_id === $organization->id && $awardProgram->status->isPublic(), 404);

        $this->programId = $awardProgram->id;
    }

    public function poll(): void
    {
        $ipHash = app(VoteFingerprintService::class)->identify(request())->ipHash;

        if (Throttle::attempt('stage', $ipHash) !== null) {
            $this->skipRender(); // Keep showing the last good state.
        }
    }

    public function render(): View
    {
        $program = AwardProgram::query()->with('organization')->findOrFail($this->programId);
        abort_unless($program->status->isPublic(), 404);

        return view('livewire.stage.stage-leaderboard', [
            'program' => $program,
            'scene' => StageScene::for($program, $this->pinned),
        ])->title("{$program->title} · Stage");
    }
}
