<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the ledger sums and the nominee delete check, chosen with EXPLAIN ANALYZE on PostgreSQL 17 against
 * 2M vote_records and 400k vote_transactions (cleanup batch 4; plan/EXECUTION_LOG.md):
 *
 * - (award_category_id, nominee_id, quantity): the per-category sum every recount, reveal and drift audit runs,
 *   index-only. 76 ms → 1 ms.
 * - (award_program_id, cast_at, nominee_id, quantity): the dashboard's total and today's votes, the results
 *   auditor and the CSV export, all index-only. 66–97 ms → 0.03–13 ms.
 * - vote_transactions (nominee_id): "has this nominee any payment?" before a delete, and PostgreSQL's own
 *   foreign-key check on delete, which scanned every payment. 168 ms → 0.03 ms.
 *
 * Built before launch, so a plain CREATE INDEX is fine. After launch, index migrations use
 * CREATE INDEX CONCURRENTLY outside a transaction (README, production checklist).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('vote_records', static function (Blueprint $table): void {
            $table->index(['award_category_id', 'nominee_id', 'quantity']);
            $table->index(['award_program_id', 'cast_at', 'nominee_id', 'quantity']);
        });

        Schema::table('vote_transactions', static function (Blueprint $table): void {
            $table->index('nominee_id');
        });
    }

    public function down(): void
    {
        Schema::table('vote_transactions', static function (Blueprint $table): void {
            $table->dropIndex(['nominee_id']);
        });

        Schema::table('vote_records', static function (Blueprint $table): void {
            $table->dropIndex(['award_program_id', 'cast_at', 'nominee_id', 'quantity']);
            $table->dropIndex(['award_category_id', 'nominee_id', 'quantity']);
        });
    }
};
