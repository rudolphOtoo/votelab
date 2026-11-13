<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', static function (Blueprint $table): void {
            // Paystack subaccount (ACCT_...) that receives 100% of paid votes. Null: paid votes are blocked.
            $table->string('paystack_subaccount_code', 64)->nullable()->after('logo_url');
        });

        Schema::table('vote_transactions', static function (Blueprint $table): void {
            $table->renameColumn('fee_amount', 'processing_fee_pesewas');
        });

        Schema::table('vote_transactions', static function (Blueprint $table): void {
            // Snapshot of where this payment settled, for audit even if the organizer changes it later.
            $table->string('settlement_subaccount_code', 64)->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('vote_transactions', static function (Blueprint $table): void {
            $table->dropColumn('settlement_subaccount_code');
        });

        Schema::table('vote_transactions', static function (Blueprint $table): void {
            $table->renameColumn('processing_fee_pesewas', 'fee_amount');
        });

        Schema::table('organizations', static function (Blueprint $table): void {
            $table->dropColumn('paystack_subaccount_code');
        });
    }
};
