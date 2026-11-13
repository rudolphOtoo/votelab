<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Append-only organizer audit trail (D-90). The app never updates or deletes rows (AuditEvent refuses).
        // No foreign keys on purpose: history must outlive the users, organizations and records it mentions.
        Schema::create('audit_events', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable(); // null = system / CLI
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable(); // e.g. award_category
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable(); // The organizer's request IP; null from the CLI
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('actor_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
