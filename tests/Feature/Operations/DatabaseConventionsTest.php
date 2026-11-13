<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Database conventions the production checklist relies on (D-137, cleanup batch 4).
 */

it('keeps the app timezone UTC, which every stored timestamp assumes', function (): void {
    expect(config('app.timezone'))->toBe('UTC');
});

it('has the ledger and payment indexes the hot queries use', function (): void {
    expect(Schema::hasIndex('vote_records', ['award_category_id', 'nominee_id', 'quantity']))
        ->toBeTrue()
        ->and(Schema::hasIndex('vote_records', ['award_program_id', 'cast_at', 'nominee_id', 'quantity']))
        ->toBeTrue()
        ->and(Schema::hasIndex('vote_transactions', ['nominee_id']))
        ->toBeTrue();
});

it('stores every JSON column as jsonb on PostgreSQL', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL only.');
    }

    $types = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->whereIn('data_type', ['json', 'jsonb'])
        ->pluck('data_type')
        ->unique()
        ->values()
        ->all();

    expect($types)->toBe(['jsonb']);
});
