<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\ArchiveNomineeAction;
use App\Actions\Admin\CreateNomineeAction;
use App\Actions\Admin\ReorderAction;
use App\Actions\Admin\UpdateNomineeAction;
use App\Enums\CategoryStatus;
use App\Livewire\Admin\Concerns\ScopedToProgram;
use App\Livewire\Admin\Forms\NomineeForm;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Support\Organizer;
use App\Support\PermissionLookups;
use App\Support\ShareLinks;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Nominees of one category: add, edit (each field by its own permission), 4:5 crop with a live preview,
 * reorder, archive, and the share kit. The server re-checks every change through the actions' policies.
 */
#[Layout('layouts::dashboard')]
final class NomineeManager extends Component
{
    use ScopedToProgram;
    use WithFileUploads;

    #[Locked]
    public int $categoryId;

    /** null = list; 0 = adding; otherwise the nominee being edited */
    public ?int $editingId = null;

    public NomineeForm $form;

    public ?string $notice = null;

    public function mount(AwardProgram $program, AwardCategory $category): void
    {
        $this->bootProgram($program);
        abort_unless($category->award_program_id === $program->id, 404);
        $this->categoryId = $category->id;
    }

    public function open(?int $nomineeId = null): void
    {
        if ($nomineeId === null) {
            $this->form->reset();
            $this->editingId = 0;

            return;
        }

        $nominee = $this->nominee($nomineeId);
        $this->form->fillFrom($nominee);
        $this->editingId = $nominee->id;
    }

    public function save(CreateNomineeAction $create, UpdateNomineeAction $update): void
    {
        $actor = Organizer::user();

        if ($this->editingId === 0) {
            $create->execute($this->category(), $actor, $this->form->forCreate());
            $this->notice = 'Nominee added.';
        } elseif ($this->editingId !== null) {
            $nominee = $this->nominee($this->editingId);
            $update->execute($nominee, $actor, $this->form->changesFrom($nominee));
            $this->notice = 'Nominee saved.';
        }

        $this->editingId = null;
        $this->form->reset();
    }

    public function move(int $nomineeId, int $direction, ReorderAction $reorder): void
    {
        $reorder->nominee($this->nominee($nomineeId), Organizer::user(), $direction);
    }

    public function archive(int $nomineeId, ArchiveNomineeAction $archive): void
    {
        $archive->execute($this->nominee($nomineeId), Organizer::user());
        $this->notice = 'Nominee archived. Their votes are kept; new votes are refused.';
    }

    /**
     * Livewire hook: the nominee list asks each nominee's category status several times; read it once while
     * this render runs (PermissionLookups).
     */
    public function rendering(): void
    {
        PermissionLookups::rememberCategoryStatuses();
    }

    public function rendered(): void
    {
        PermissionLookups::forgetCategoryStatuses();
    }

    public function render(): View
    {
        $program = $this->program();
        $category = $this->category($program);
        // The share kit reads each nominee's category, program and organization: hand over the ones loaded here.
        $nominees = $category
            ->nominees()
            ->get()
            ->each(
                static fn(Nominee $n) => $n->setRelation('awardCategory', $category)->setRelation(
                    'awardProgram',
                    $program,
                ),
            );
        $editing = $this->editingId ? $nominees->firstWhere('id', $this->editingId) : null;
        $shareable = $category->status !== CategoryStatus::Draft && $program->status->isPublic();

        return view('livewire.admin.nominee-manager', [
            'program' => $program,
            'category' => $category,
            'nominees' => $nominees,
            'editing' => $editing,
            'canCreate' => Organizer::user()->can('create', [Nominee::class, $category]),
            'cropSource' =>
                $this->form->photo?->temporaryUrl()
                    ?? (
                        $editing?->photo_original_path !== null ? route('dashboard.nominees.original', $editing) : null
                    ),
            'shares' => $shareable
                ? $nominees
                    ->reject(static fn(Nominee $n): bool => $n->isArchived())
                    ->mapWithKeys(static fn(Nominee $n): array => [$n->id => ShareLinks::for($n)])
                : collect(),
        ])->title("{$category->name} · Nominees");
    }

    private function category(?AwardProgram $program = null): AwardCategory
    {
        $program ??= $this->program();

        return AwardCategory::query()->where('award_program_id', $program->id)->findOrFail($this->categoryId);
    }

    private function nominee(int $nomineeId): Nominee
    {
        return Nominee::query()->where('award_category_id', $this->category()->id)->findOrFail($nomineeId);
    }
}
