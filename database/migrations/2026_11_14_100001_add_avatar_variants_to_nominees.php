<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smaller portrait sizes for srcset (D-127): {"400": url, "240": url}, rendered with the 800×1000 portrait from
 * the same original and crop. Null until generated (new uploads, re-crops, or nominees:photo-variants).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('nominees', static function (Blueprint $table): void {
            $table->json('avatar_variants')->nullable()->after('avatar_url');
        });
    }

    public function down(): void
    {
        Schema::table('nominees', static function (Blueprint $table): void {
            $table->dropColumn('avatar_variants');
        });
    }
};
