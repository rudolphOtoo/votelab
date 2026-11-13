<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Invite-only onboarding. Each row is its own audit trail: who invited, who accepted or revoked, when.
        Schema::create('organization_invitations', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email'); // Trimmed and lower-cased
            $table->string('role'); // OrganizationRole
            $table->char('token_hash', 64)->unique(); // SHA-256 of the emailed token; the token itself is never stored
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete(); // null = CLI
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoked_reason')->nullable(); // revoked | superseded
            $table->timestamps();

            $table->index(['organization_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
