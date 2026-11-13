<?php

declare(strict_types=1);

namespace App\Actions\Reporting;

use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use OpenSpout\Writer\WriterInterface;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reconciled results as CSV: one row per nominee with its total from vote_records (the ledger), not the
 * derived counter. Streamed: totals are aggregated in SQL and rows are read with a cursor, so memory stays
 * flat however many votes there are. No voter personal data. Owners and admins only (results.export), who
 * may also see hidden results, so the export includes them; every export is audited with its scope and
 * whether hidden results were included.
 */
final readonly class ExportResultsAction
{
    public const array HEADER = ['Category', 'Nominee Code', 'Candidate Name', 'Total Votes', 'Archived'];

    /**
     * @throws AuthorizationException
     */
    public function execute(AwardProgram $program, User $actor, ?AwardCategory $category = null): StreamedResponse
    {
        Gate::forUser($actor)->authorize('exportResults', $program);
        abort_if($category !== null && $category->award_program_id !== $program->id, 404);

        $categories = $program
            ->categories()
            ->when($category, static fn($q) => $q->whereKey($category?->id))
            ->get(['id', 'status']);
        $includedHidden = $categories->contains(
            static fn(AwardCategory $c): bool => !$c->status->isLeaderboardPublic(),
        );

        Audit::record(
            'results.exported',
            $actor,
            $category ?? $program,
            [],
            [
                'scope' => $category === null ? 'program' : 'category',
                'award_program_id' => $program->id,
                'award_category_id' => $category?->id,
                'included_hidden_results' => $includedHidden,
            ],
        );

        $name = Str::slug($program->title) . ($category === null ? '' : '-' . $category->slug) . '-results.csv';

        return response()->streamDownload(
            static function () use ($program, $category): void {
                $writer = SimpleExcelWriter::streamDownload(
                    'results.csv',
                    'csv',
                    static fn(WriterInterface $writer) => $writer->openToFile('php://output'),
                );
                $writer->addHeader(self::HEADER);

                foreach (self::rows($program->id, $category?->id) as $row) {
                    $writer->addRow($row);
                }

                $writer->close();
            },
            $name,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * @return \Generator<int, array<string, int|string>>
     */
    public static function rows(int $programId, ?int $categoryId): \Generator
    {
        $totals = DB::table('vote_records')
            ->selectRaw('nominee_id, SUM(quantity) as votes')
            ->where('award_program_id', $programId)
            ->groupBy('nominee_id');

        $query = DB::table('nominees')
            ->join('award_categories', 'award_categories.id', '=', 'nominees.award_category_id')
            ->leftJoinSub($totals, 'totals', 'totals.nominee_id', '=', 'nominees.id')
            ->where('nominees.award_program_id', $programId)
            ->when($categoryId, static fn($q) => $q->where('nominees.award_category_id', $categoryId))
            ->orderBy('award_categories.display_order')
            ->orderBy('award_categories.id')
            ->orderBy('nominees.display_order')
            ->orderBy('nominees.id')
            ->select([
                'award_categories.name as category',
                'nominees.nominee_code as code',
                'nominees.name as name',
                'totals.votes as votes',
                'nominees.archived_at as archived_at',
            ]);

        foreach ($query->cursor() as $row) {
            yield [
                'Category' => (string) $row->category,
                'Nominee Code' => (string) $row->code,
                'Candidate Name' => (string) $row->name,
                'Total Votes' => (int) ($row->votes ?? 0),
                'Archived' => $row->archived_at === null ? 'no' : 'yes',
            ];
        }
    }
}
