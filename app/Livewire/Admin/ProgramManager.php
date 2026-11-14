<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\ChangeProgramStatusAction;
use App\Actions\Admin\SaveProgramAction;
use App\Livewire\Admin\Concerns\ScopedToProgram;
use App\Models\AwardProgram;
use App\Support\LaunchChecklist;
use App\Support\Organizer;
use App\Support\ProgramOverview;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Program overview: details, dates, the close grace window, the launch checklist, and publish / unpublish /
 * archive. Staff see it read-only.
 */
#[Layout('layouts::dashboard')]
final class ProgramManager extends Component
{
    use ScopedToProgram;

    public string $title = '';

    public string $slug = '';

    public string $tagline = '';

    public string $description = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public int $close_grace_minutes = 30;

    public ?string $notice = null;

    public function mount(AwardProgram $program): void
    {
        $this->bootProgram($program);
        $this->fill([
            'title' => $program->title,
            'slug' => $program->slug,
            'tagline' => (string) $program->tagline,
            'description' => (string) $program->description,
            'starts_at' => (string) $program->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => (string) $program->ends_at?->format('Y-m-d\TH:i'),
            'close_grace_minutes' => $program->close_grace_minutes,
        ]);
    }

    public function save(SaveProgramAction $save): void
    {
        $save->update($this->program(), Organizer::user(), [
            'title' => $this->title,
            'slug' => $this->slug,
            'tagline' => $this->tagline === '' ? null : $this->tagline,
            'description' => $this->description === '' ? null : $this->description,
            'starts_at' => $this->starts_at === '' ? null : $this->starts_at,
            'ends_at' => $this->ends_at === '' ? null : $this->ends_at,
            'close_grace_minutes' => $this->close_grace_minutes,
        ]);

        $this->notice = 'Program saved.';
    }

    public function publish(ChangeProgramStatusAction $change): void
    {
        $change->publish($this->program(), Organizer::user());
        $this->notice = 'Published. Voters can now see the program.';
    }

    public function unpublish(ChangeProgramStatusAction $change): void
    {
        $change->unpublish($this->program(), Organizer::user());
        $this->notice = 'Unpublished.';
    }

    public function toggleHomepageListing(SaveProgramAction $save): void
    {
        $program = $save->setHiddenFromHomepage($this->program(), Organizer::user(), $this->program()->isListed());
        $this->notice = $program->isListed()
            ? 'Shown on the VoteLab homepage again.'
            : 'Hidden from the VoteLab homepage. Its own link still works.';
    }

    public function archive(ChangeProgramStatusAction $change): void
    {
        $change->archive($this->program(), Organizer::user());
        $this->notice = 'Archived.';
    }

    public function render(): View
    {
        $program = $this->program();
        $user = Organizer::user();

        return view('livewire.admin.program-manager', [
            'program' => $program,
            'tiles' => ProgramOverview::tiles($program, $user),
            'checklist' => LaunchChecklist::for($program),
            'fixLinks' => self::fixLinks($program),
            'canEdit' => $user->can('update', $program),
            'canPublish' => $user->can('publish', $program),
            'canUnpublish' => $user->can('unpublish', $program),
            'canArchive' => $user->can('archive', $program),
            'graceMin' => SaveProgramAction::minimum(),
            'graceMax' => SaveProgramAction::maximum(),
            'publicUrl' => url("/{$program->organization->slug}/{$program->slug}"),
            'hiddenFromHomepage' => !$program->isListed(),
        ])->title($program->title);
    }

    /**
     * Where each launch-checklist item is fixed.
     *
     * @return array<string, string>
     */
    private static function fixLinks(AwardProgram $program): array
    {
        $categories = route('dashboard.programs.categories', $program);
        $organization = route('dashboard.members', [$program->organization, 'tab' => 'settings']);

        return [
            'categories' => $categories,
            'nominees' => $categories,
            'photos' => $categories,
            'codes' => $categories,
            'prices' => $categories,
            'payout' => $organization,
            'dates' => '#program-details',
        ];
    }
}
