<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Admin\ReorderAction;
use App\Actions\Admin\RevealWinnerAction;
use App\Actions\Admin\SaveCategoryAction;
use App\Actions\Admin\TransitionCategoryStatusAction;
use App\Enums\CategoryStatus;
use App\Enums\VotingMode;
use App\Exceptions\Admin\PendingPaymentsException;
use App\Exceptions\Admin\StatusChangeRefusedException;
use App\Livewire\Admin\Concerns\ScopedToProgram;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Support\Money;
use App\Support\Organizer;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Categories of one program: create, edit (details any time until revealed, pricing in Draft), reorder, and
 * status changes. Reveal shows how many payments are still pending and needs an explicit "reveal anyway"
 * plus a recently confirmed password to override them. Every button appears only if the policy allows it.
 */
#[Layout('layouts::dashboard')]
final class CategoryManager extends Component
{
    use ScopedToProgram;

    public ?int $editingId = null;

    /** @var array{name: string, slug: string, description: string, voting_mode: string, price: string, free_vote_limit: int} */
    public array $form = [
        'name' => '',
        'slug' => '',
        'description' => '',
        'voting_mode' => 'paid',
        'price' => '1.00',
        'free_vote_limit' => 1,
    ];

    public bool $revealAnyway = false;

    public ?string $notice = null;

    public function mount(AwardProgram $program): void
    {
        $this->bootProgram($program);
    }

    public function create(): void
    {
        $this->editingId = 0;
        $this->form = [
            'name' => '',
            'slug' => '',
            'description' => '',
            'voting_mode' => 'paid',
            'price' => '1.00',
            'free_vote_limit' => 1,
        ];
    }

    public function edit(int $categoryId): void
    {
        $category = $this->category($categoryId);
        $this->editingId = $category->id;
        $this->form = [
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => (string) $category->description,
            'voting_mode' => $category->voting_mode->value,
            'price' => number_format($category->price_per_vote / 100, 2, '.', ''),
            'free_vote_limit' => $category->free_vote_limit,
        ];
    }

    public function cancel(): void
    {
        $this->editingId = null;
    }

    public function save(SaveCategoryAction $save): void
    {
        $price = Money::parsePesewas($this->form['price']);

        if ($price === null) {
            throw ValidationException::withMessages([
                'form.price' => 'Enter a price like 1.00 (cedis, up to two decimals).',
            ]);
        }

        $input = [
            'name' => $this->form['name'],
            'slug' => $this->form['slug'],
            'description' => $this->form['description'] === '' ? null : $this->form['description'],
            'voting_mode' => $this->form['voting_mode'],
            'price_per_vote' => $price,
            'free_vote_limit' => (int) $this->form['free_vote_limit'],
        ];

        if ($this->editingId === 0) {
            $save->create($this->program(), Organizer::user(), $input);
            $this->notice = 'Category added.';
        } elseif ($this->editingId !== null) {
            $category = $this->category($this->editingId);
            // Only send pricing fields if they changed, so details stay editable after Draft.
            $current = [
                'slug' => $category->slug,
                'voting_mode' => $category->voting_mode->value,
                'price_per_vote' => $category->price_per_vote,
                'free_vote_limit' => $category->free_vote_limit,
            ];
            $save->update(
                $category,
                Organizer::user(),
                array_filter(
                    $input,
                    static fn(mixed $value, string $key): bool => (
                        !array_key_exists($key, $current)
                        || $current[$key] !== $value
                    ),
                    ARRAY_FILTER_USE_BOTH,
                ),
            );
            $this->notice = 'Category saved.';
        }

        $this->editingId = null;
    }

    public function move(int $categoryId, int $direction, ReorderAction $reorder): void
    {
        $reorder->category($this->category($categoryId), Organizer::user(), $direction);
    }

    public function changeStatus(int $categoryId, string $to, TransitionCategoryStatusAction $transition): void
    {
        try {
            $transition->execute($this->category($categoryId), CategoryStatus::from($to), Organizer::user());
            $this->notice = 'Status updated.';
        } catch (StatusChangeRefusedException $e) {
            $this->addError('status', $e->getMessage());
        }
    }

    public function reveal(int $categoryId, RevealWinnerAction $reveal): void
    {
        $confirmed = Organizer::passwordRecentlyConfirmed();

        if ($this->revealAnyway && !$confirmed) {
            session()->put('url.intended', route('dashboard.programs.categories', $this->programId));
            $this->redirectRoute('password.confirm');

            return;
        }

        try {
            $result = $reveal->execute(
                $this->category($categoryId),
                Organizer::user(),
                $this->revealAnyway,
                $confirmed,
            );
            $this->notice = $result->pendingPaymentsOverridden > 0
                ? "Revealed. {$result->pendingPaymentsOverridden} pending payment(s) will be refunded if they complete."
                : 'Revealed.';
        } catch (PendingPaymentsException $e) {
            $this->addError('status', $e->getMessage());
        }

        $this->revealAnyway = false;
    }

    public function render(): View
    {
        $program = $this->program();
        $categories = $program
            ->categories()
            ->withCount(['nominees' => static fn($q) => $q->whereNull('archived_at')])
            ->get();

        return view('livewire.admin.category-manager', [
            'program' => $program,
            'categories' => $categories,
            'pending' => $categories->mapWithKeys(static fn(AwardCategory $c): array => [
                $c->id => RevealWinnerAction::pendingPaymentsCount($c),
            ]),
            'canCreate' => Organizer::user()->can('create', [AwardCategory::class, $program]),
            'modes' => VotingMode::cases(),
            'moves' => [
                'open' => CategoryStatus::Open,
                'embargo' => CategoryStatus::Embargoed,
                'close' => CategoryStatus::Closed,
            ],
        ])->title("Categories · {$program->title}");
    }

    private function category(int $categoryId): AwardCategory
    {
        return AwardCategory::query()->where('award_program_id', $this->program()->id)->findOrFail($categoryId);
    }
}
