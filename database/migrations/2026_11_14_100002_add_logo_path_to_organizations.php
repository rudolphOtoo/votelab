<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an uploaded logo lives on the public disk (D-131), so share images can draw it and a replaced logo is
 * deleted. Null for a logo given as an external URL, or none.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', static function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('logo_url');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', static function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
