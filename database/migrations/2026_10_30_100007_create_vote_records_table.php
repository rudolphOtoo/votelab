<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vote_records', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('award_program_id')->constrained()->restrictOnDelete();
            $table->foreignId('award_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('nominee_id')->constrained()->restrictOnDelete();
            // Unique: a paid transaction can be credited at most once, enforced by the database.
            $table->foreignId('vote_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->boolean('is_paid')->default(false);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('voter_fingerprint', 128)->nullable();
            $table->string('ip_hash', 64)->nullable(); // HMAC of the IP; the raw address is never stored
            $table->timestamp('cast_at')->useCurrent();

            $table->index(['voter_fingerprint', 'award_category_id']);
            $table->index(['nominee_id', 'cast_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vote_records');
    }
};
