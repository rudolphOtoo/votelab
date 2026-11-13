<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nominees', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('award_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('award_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('stage_name')->nullable();
            $table->string('nominee_code', 32);
            $table->string('avatar_url')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedBigInteger('votes_count')->default(0); // Derived counter; the ledger is vote_records
            $table->integer('display_order')->default(0);
            $table->timestamps();

            // Codes are unique across the whole program so a voter can quote one without naming the category.
            $table->unique(['award_program_id', 'nominee_code']);
            $table->index(['award_category_id', 'votes_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nominees');
    }
};
