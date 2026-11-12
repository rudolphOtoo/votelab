<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\SaveProgramAction;
use App\Models\AwardProgram;
use App\Models\Organization;
use App\Support\Organizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts::dashboard')]
final class ProgramCreate extends Component
{
    #[Locked]
    public int $organizationId;

    public string $title = '';

    public string $slug = '';

    public function mount(Organization $organization): void
    {
        Gate::authorize('create', [AwardProgram::class, $organization]);
        $this->organizationId = $organization->id;
    }

    public function updatedTitle(): void
    {
        if ($this->slug === '') {
            $this->slug = Str::slug($this->title);
        }
    }

    public function save(SaveProgramAction $save): void
    {
        $organization = Organization::query()->findOrFail($this->organizationId);
        $program = $save->create($organization, Organizer::user(), ['title' => $this->title, 'slug' => $this->slug]);

        $this->redirectRoute('dashboard.programs.show', $program);
    }

    public function render(): View
    {
        return view('livewire.admin.program-create', [
            'organization' => Organization::query()->findOrFail($this->organizationId),
        ])->title('New program');
    }
}
