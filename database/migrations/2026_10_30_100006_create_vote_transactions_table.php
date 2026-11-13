<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vote_transactions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('award_program_id')->constrained()->restrictOnDelete();
            $table->foreignId('award_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('nominee_id')->constrained()->restrictOnDelete();

            // Snapshotted at checkout; fulfilment credits these values and never recalculates.
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price'); // Pesewas
            $table->unsignedInteger('gross_amount'); // Pesewas charged to the voter
            $table->unsignedInteger('fee_amount'); // Pesewas of gateway fee covered by the voter
            $table->unsignedInteger('net_amount'); // Pesewas owed to the organizer (quantity x unit_price)
            $table->char('currency', 3)->default('GHS');

            $table->string('payment_provider')->default('paystack');
            $table->string('payment_reference')->unique();
            $table->string('status')->default('pending'); // pending, successful, failed, flagged, paid_uncredited, refunded
            $table->string('status_reason')->nullable();
            $table->string('voter_email')->nullable(); // Only when the voter supplied one; never a phone number
            $table->json('metadata')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['award_category_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vote_transactions');
    }
};
