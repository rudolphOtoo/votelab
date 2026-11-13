<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Models\AwardCategory;
use App\Models\Nominee;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use Database\Seeders\DatabaseSeeder;

it('seeds a demo program whose counters match the vote ledger', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Nominee::query()->count())->toBeGreaterThan(0);

    Nominee::query()->each(function (Nominee $nominee): void {
        expect($nominee->votes_count)->toBe((int) $nominee->voteRecords()->sum('quantity'), $nominee->name);
    });
});

it('seeds every category lifecycle state except revealed', function (): void {
    $this->seed(DatabaseSeeder::class);

    $statuses = AwardCategory::query()->pluck('status')->unique()->values()->all();

    expect($statuses)->toContain(
        CategoryStatus::Draft,
        CategoryStatus::Open,
        CategoryStatus::Embargoed,
        CategoryStatus::Closed,
    );
});

it('never seeds votes into draft categories', function (): void {
    $this->seed(DatabaseSeeder::class);

    $draftIds = AwardCategory::query()->where('status', CategoryStatus::Draft)->pluck('id');

    expect(VoteRecord::query()->whereIn('award_category_id', $draftIds)->count())
        ->toBe(0)
        ->and(VoteTransaction::query()->whereIn('award_category_id', $draftIds)->count())
        ->toBe(0);
});
