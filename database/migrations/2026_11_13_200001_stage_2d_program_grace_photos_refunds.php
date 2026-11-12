<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // D-94: owners/admins set the close grace window per program (10 to VOTING_CLOSE_GRACE_MINUTES).
        Schema::table('award_programs', static function (Blueprint $table): void {
            $table->unsignedSmallInteger('close_grace_minutes')->default(30)->after('ends_at');
        });

        // The program's value is copied here when the category closes, so a later edit can't move a window
        // that is already running.
        Schema::table('award_categories', static function (Blueprint $table): void {
            $table->unsignedSmallInteger('close_grace_minutes')->nullable()->after('closed_at');
        });

        // The uploaded original (private disk) and the organizer's 4:5 crop of it, so the portrait can be
        // re-cropped later and is always re-encoded from the original.
        Schema::table('nominees', static function (Blueprint $table): void {
            $table->string('photo_original_path')->nullable()->after('avatar_url');
            $table->json('photo_crop')->nullable()->after('photo_original_path');
        });

        // Append-only record that a paid_uncredited payment was refunded (outside VoteLab; no money moves here).
        Schema::create('refund_records', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vote_transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->string('paystack_refund_reference', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_records');

        Schema::table('nominees', static function (Blueprint $table): void {
            $table->dropColumn(['photo_original_path', 'photo_crop']);
        });

        Schema::table('award_categories', static function (Blueprint $table): void {
            $table->dropColumn('close_grace_minutes');
        });

        Schema::table('award_programs', static function (Blueprint $table): void {
            $table->dropColumn('close_grace_minutes');
        });
    }
};
