<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Actions\Reporting\FindTransactionsAction;
use App\Actions\Reporting\RecordRefundAction;
use App\Actions\Voting\SummarizeProgramRevenueAction;
use App\Livewire\Admin\Concerns\ScopedToProgram;
use App\Models\AuditEvent;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\VoteTransaction;
use App\Support\Organizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Results, money and history for one program. Each tab needs its own ability:
 * - results: counts from nominees.votes_count checked against vote_records (the ledger), drift flagged;
 *   hidden results only with results.viewHidden (owners/admins), public ones for everyone;
 * - transactions (transactions.view): masked recent list and audited exact lookups;
 * - refunds (refunds.view; recording needs refunds.record, owners, plus a confirmed password);
 * - revenue (revenue.view); history (results.audit): the audit_events trail; export (results.export).
 */
#[Layout('layouts::dashboard')]
final class ResultsAuditor extends Component
{
    use ScopedToProgram;
    use WithPagination;

    #[Url]
    public string $tab = 'results';

    public string $search = '';

    /** @var list<array<string, mixed>> Lookup results, as plain arrays (Livewire state). */
    public array $found = [];

    public bool $searched = false;

    public ?int $refundingId = null;

    public string $refundReason = '';

    public string $refundReference = '';

    #[Url]
    public string $action = '';

    public ?string $notice = null;

    public function mount(AwardProgram $program): void
    {
        $this->bootProgram($program);
    }

    public function lookup(FindTransactionsAction $find): void
    {
        $this->found = array_map(
            get_object_vars(...),
            $find->lookup($this->program(), Organizer::user(), $this->search),
        );
        $this->searched = true;
    }

    public function startRefund(int $transactionId): void
    {
        $this->refundingId = VoteTransaction::query()
            ->where('award_program_id', $this->programId)
            ->findOrFail($transactionId)
            ->id;
        $this->reset('refundReason', 'refundReference');
    }

    public function recordRefund(RecordRefundAction $refunds): void
    {
        if (!Organizer::passwordRecentlyConfirmed()) {
            session()->put('url.intended', route('dashboard.programs.results', [$this->programId, 'tab' => 'refunds']));
            $this->redirectRoute('password.confirm');

            return;
        }

        $transaction = VoteTransaction::query()
            ->where('award_program_id', $this->programId)
            ->findOrFail((int) $this->refundingId);
        $refunds->record(
            $transaction,
            Organizer::user(),
            $this->refundReason,
            $this->refundReference,
            passwordConfirmed: true,
        );
        $this->refundingId = null;
        $this->notice = 'Refund recorded.';
    }

    public function render(): View
    {
        $program = $this->program();
        $user = Organizer::user();
        $tabs = array_filter([
            'results' => 'Results',
            'transactions' => $user->can('viewTransactions', $program) ? 'Transactions' : null,
            'refunds' => $user->can('viewRefunds', $program) ? 'Refunds' : null,
            'revenue' => $user->can('viewRevenue', $program) ? 'Revenue' : null,
            'history' => $user->can('auditResults', $program) ? 'History' : null,
        ]);
        $tab = array_key_exists($this->tab, $tabs) ? $this->tab : 'results';

        return view('livewire.admin.results-auditor', [
            'program' => $program,
            'tabs' => $tabs,
            'current' => $tab,
            'canExport' => $user->can('exportResults', $program),
            'canRecordRefunds' => $user->can('recordRefunds', $program),
            ...match ($tab) {
                'transactions' => ['recent' => array_map(
                    get_object_vars(...),
                    app(FindTransactionsAction::class)->recent($program, $user, 25),
                )],
                'refunds' => ['queue' => app(RecordRefundAction::class)->queue($program, $user)],
                'revenue' => ['revenue' => app(SummarizeProgramRevenueAction::class)->execute($program)],
                'history' => ['events' => $this->history($program)],
                default => ['categories' => $this->results($program)],
            },
        ])->title("Results · {$program->title}");
    }

    /**
     * Per category: each nominee's counter and ledger total, or null counts when this user can't see them.
     *
     * @return list<array{category: AwardCategory, visible: bool, rows: list<array{name: string, code: string, archived: bool, counter: int, ledger: int}>}>
     */
    private function results(AwardProgram $program): array
    {
        $user = Organizer::user();
        $ledger = DB::table('vote_records')
            ->where('award_program_id', $program->id)
            ->groupBy('nominee_id')
            ->selectRaw('nominee_id, SUM(quantity) as votes')
            ->pluck('votes', 'nominee_id');
        $out = [];

        foreach ($program->categories()->with('nominees')->get() as $category) {
            $visible = $user->can('viewResults', $category) || $user->can('viewHiddenResults', $category);
            $rows = [];

            foreach ($category->nominees->sortByDesc('votes_count') as $nominee) {
                $rows[] = [
                    'name' => $nominee->displayName(),
                    'code' => $nominee->nominee_code,
                    'archived' => $nominee->isArchived(),
                    'counter' => $visible ? $nominee->votes_count : 0,
                    'ledger' => $visible ? (int) ($ledger[$nominee->id] ?? 0) : 0,
                ];
            }

            $out[] = [
                'category' => $category,
                'visible' => $visible,
                'rows' => $visible
                    ? $rows
                    : array_map(static fn(array $r): array => [...$r, 'counter' => 0, 'ledger' => 0], $rows),
            ];
        }

        return $out;
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, AuditEvent>
     */
    private function history(AwardProgram $program): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return AuditEvent::query()
            ->where('organization_id', $program->organization_id)
            ->when($this->action !== '', fn($q) => $q->where('action', 'like', $this->action . '%'))
            ->latest('id')
            ->paginate(25);
    }
}
