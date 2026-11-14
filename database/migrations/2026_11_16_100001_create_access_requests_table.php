<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organizer "Request access" submissions (plan/SITE.md decision 8). Contact details are kept in plain text so we can
 * reply: a deliberate exception to D-86, which covers voters. Deleted 180 days after they arrive (model:prune).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('access_requests', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('organization', 160);
            $table->string('email', 254);
            $table->string('phone', 32);
            $table->string('event_type', 32);
            $table->date('expected_date')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_requests');
    }
};
