<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('award_categories', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('award_program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('voting_mode')->default('paid'); // free, paid, hybrid
            $table->unsignedInteger('price_per_vote')->default(100); // Pesewas (100 = GHS 1.00)
            $table->unsignedInteger('free_vote_limit')->default(1);
            $table->string('status')->default('draft'); // draft, open, embargoed, closed, revealed
            $table->timestamp('closed_at')->nullable(); // Start of the close grace window
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->unique(['award_program_id', 'slug']);
            $table->index(['award_program_id', 'status', 'display_order']);
        });

        Schema::create('category_status_transitions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('award_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['award_category_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_status_transitions');
        Schema::dropIfExists('award_categories');
    }
};
