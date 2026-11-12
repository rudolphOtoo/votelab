<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Archived nominees are hidden and take no new votes; their votes stay in the ledger and the results.
        Schema::table('nominees', static function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('display_order');
            $table
                ->foreignId('archived_by_user_id')
                ->nullable()
                ->after('archived_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        // D-12 stands: no phone number is stored. phone_hash = HMAC-SHA256(VOTING_PHONE_HASH_KEY, 233XXXXXXXXX)
        // allows exact search; phone_last3 is for masked display only.
        Schema::table('vote_transactions', static function (Blueprint $table): void {
            $table->char('phone_hash', 64)->nullable()->after('voter_email');
            $table->char('phone_last3', 3)->nullable()->after('phone_hash');

            $table->index(['award_program_id', 'phone_hash']);
            $table->index(['award_program_id', 'voter_email']);
        });

        Schema::table('category_status_transitions', static function (Blueprint $table): void {
            $table->string('reason')->nullable()->after('to_status');
        });
    }

    public function down(): void
    {
        Schema::table('category_status_transitions', static function (Blueprint $table): void {
            $table->dropColumn('reason');
        });

        Schema::table('vote_transactions', static function (Blueprint $table): void {
            $table->dropIndex(['award_program_id', 'phone_hash']);
            $table->dropIndex(['award_program_id', 'voter_email']);
            $table->dropColumn(['phone_hash', 'phone_last3']);
        });

        Schema::table('nominees', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('archived_by_user_id');
            $table->dropColumn('archived_at');
        });
    }
};
