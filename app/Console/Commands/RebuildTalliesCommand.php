<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\TallyAudit;
use App\Enums\ProgramStatus;
use App\Models\AwardProgram;
use App\Support\Observability\Alert;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Compares every nominee's derived votes_count with vote_records, the ledger (D-05), and reports the drift
 * per nominee. Without --dry-run it rewrites the counters from the ledger and refreshes the leaderboard
 * cache. Every drift is logged at error level, alerted and audited (RecountCategoryTallies, D-92). The
 * scheduler runs `--all --dry-run` nightly; any drift then raises a summary alert. Exits 1 when drift was
 * found, so monitoring can watch it.
 */
final class RebuildTalliesCommand extends Command
{
    protected $signature = 'votes:rebuild-tallies
        {program? : Program id, or organization-slug/program-slug}
        {--all : Every program that is not archived}
        {--dry-run : Report drift without changing anything}
        {--force : Required to fix counters in production}';

    protected $description = 'Recount nominee tallies from the vote ledger and report (or fix) any drift';

    public function handle(): int
    {
        $programs = $this->programs();

        if ($programs === null) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (!$dryRun && $this->laravel->isProduction() && !$this->option('force')) {
            $this->error('Refusing to change counters in production without --force (use --dry-run to inspect).');

            return self::FAILURE;
        }

        $rows = TallyAudit::run($programs, $dryRun);

        if ($rows === []) {
            $this->info("No drift in {$programs->count()} program(s).");

            return self::SUCCESS;
        }

        $this->table(['Program', 'Category', 'Nominee', 'Counter', 'Ledger', 'Action'], $rows);

        if ($dryRun && $this->option('all')) {
            Alert::raise('tally.drift_nightly', ['nominees' => count($rows), 'programs' => $programs->count()]);
        }

        $this->warn(
            count($rows)
            . ' nominee(s) drifted from the ledger'
            . ($dryRun ? '; nothing changed (dry run).' : '; counters fixed from the ledger.'),
        );

        return self::FAILURE;
    }

    /**
     * @return Collection<int, AwardProgram>|null
     */
    private function programs(): ?Collection
    {
        if ($this->option('all')) {
            return AwardProgram::query()->where('status', '!=', ProgramStatus::Archived)->orderBy('id')->get();
        }

        $given = (string) $this->argument('program');

        if ($given === '') {
            $this->error('Name a program (id or organization-slug/program-slug) or use --all.');

            return null;
        }

        $program = AwardProgram::findByReference($given);

        if ($program === null) {
            $this->error("Program \"{$given}\" not found.");

            return null;
        }

        return collect([$program]);
    }
}
