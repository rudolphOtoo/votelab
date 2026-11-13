<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            // Starts the two-factor grace period for owners and admins (config/organizations.php).
            $table->timestamp('first_signed_in_at')->nullable()->after('remember_token');
            // Bumped when the password changes or two-factor is turned on; sessions holding an older
            // value are signed out (App\Support\SessionEpoch).
            $table->unsignedInteger('session_epoch')->default(0)->after('first_signed_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn(['first_signed_in_at', 'session_epoch']);
        });
    }
};
