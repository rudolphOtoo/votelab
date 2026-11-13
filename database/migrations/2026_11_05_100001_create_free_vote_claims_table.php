<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // One row per free vote. The unique key caps free votes per voter, per category, per day in the
        // database itself: slot n can only be claimed once, and slots run from 1 to free_vote_limit.
        Schema::create('free_vote_claims', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('award_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vote_record_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('voter_key', 64); // HMAC-SHA256(ip | user agent | device cookie)
            $table->date('period'); // Calendar day in the app timezone (UTC = Ghana time)
            $table->unsignedSmallInteger('slot');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['award_category_id', 'voter_key', 'period', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_vote_claims');
    }
};
