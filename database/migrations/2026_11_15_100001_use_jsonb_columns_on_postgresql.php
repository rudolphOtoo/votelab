<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL only (D-137): JSON columns become jsonb, PostgreSQL's binary JSON type (compared, indexed and
 * queried by path; `json` is stored as text and can't be compared). Values keep their meaning; jsonb doesn't keep
 * key order or duplicate keys, which nothing relies on. Other engines are untouched.
 */
return new class extends Migration {
    /** @var array<string, list<string>> */
    private const array COLUMNS = [
        'audit_events' => ['before', 'after', 'context'],
        'award_programs' => ['settings'],
        'nominees' => ['avatar_variants', 'photo_crop'],
        'organizations' => ['settings'],
        'passkeys' => ['credential'],
        'vote_transactions' => ['metadata'],
    ];

    public function up(): void
    {
        $this->convert('jsonb');
    }

    public function down(): void
    {
        $this->convert('json');
    }

    private function convert(string $type): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::COLUMNS as $table => $columns) {
            $changes = implode(', ', array_map(
                static fn(string $column): string => "ALTER COLUMN \"{$column}\" TYPE {$type} USING \"{$column}\"::{$type}",
                $columns,
            ));
            DB::statement("ALTER TABLE \"{$table}\" {$changes}");
        }
    }
};
