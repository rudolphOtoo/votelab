<?php

declare(strict_types=1);

use App\Actions\Voting\CastFreeVoteAction;
use App\Actions\Voting\FulfillPaidVoteOrderAction;
use App\Enums\CategoryStatus;
use App\Enums\PaymentTrigger;
use App\Enums\VotingMode;
use App\Events\FreeVoteCast;
use App\Events\VotesCredited;
use App\Listeners\RefreshLeaderboardCache;
use App\Models\AwardCategory;
use App\Models\Nominee;
use App\Models\VoteTransaction;
use App\Services\Security\VoterIdentity;
use App\Support\LeaderboardCache;
use App\Support\VoteKeys;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(fn() => config(['voting.leaderboard_cache_seconds' => 3_600]));

it('registers the refresh listener for paid and free votes', function (): void {
    Event::fake();

    Event::assertListening(VotesCredited::class, [RefreshLeaderboardCache::class, 'handleVotesCredited']);
    Event::assertListening(FreeVoteCast::class, [RefreshLeaderboardCache::class, 'handleFreeVoteCast']);
});

it('serves polls from the cache: only the status gate queries, never the standings', function (): void {
    $nominee = Nominee::factory()->create();
    LeaderboardCache::standings($nominee->awardCategory);

    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    LeaderboardCache::standings($nominee->awardCategory);

    // D-67 (amended): one primary-key read of the status column, and nothing else.
    expect($queries)->toBe(['select "status" from "award_categories" where "award_categories"."id" = ? limit 1']);
});

it('refreshes cached standings as soon as paid votes are credited', function (): void {
    $gateway = fakeGateway();
    $transaction = VoteTransaction::factory()->create(['quantity' => 40]);
    $gateway->reports($transaction->payment_reference, amount: $transaction->gross_amount);

    expect(LeaderboardCache::standings($transaction->awardCategory)[$transaction->nominee_id])->toBe(0);

    app(FulfillPaidVoteOrderAction::class)->execute($transaction->payment_reference, PaymentTrigger::Webhook);

    expect(LeaderboardCache::standings($transaction->awardCategory)[$transaction->nominee_id])->toBe(40);
});

it('refreshes cached standings as soon as a free vote is cast', function (): void {
    $nominee = Nominee::factory()->for(AwardCategory::factory()->mode(VotingMode::Free))->create();
    expect(LeaderboardCache::standings($nominee->awardCategory)[$nominee->id])->toBe(0);

    app(CastFreeVoteAction::class)->execute($nominee, new VoterIdentity(str_repeat('a', 64), str_repeat('b', 64)));

    expect(LeaderboardCache::standings($nominee->awardCategory)[$nominee->id])->toBe(1);
});

it('marks standings stale when votes land but keeps the last good copy', function (): void {
    $nominee = Nominee::factory()->for(AwardCategory::factory()->mode(VotingMode::Free))->create();
    LeaderboardCache::standings($nominee->awardCategory);

    app(CastFreeVoteAction::class)->execute($nominee, new VoterIdentity(str_repeat('a', 64), str_repeat('b', 64)));

    expect(Cache::has(VoteKeys::leaderboardFresh($nominee->award_category_id)))
        ->toBeFalse()
        ->and(Cache::get(VoteKeys::leaderboard($nominee->award_category_id)))
        ->toBe([$nominee->id => 0]);
});

/**
 * Counts the standings rebuild query (one indexed read of nominees.votes_count).
 */
function countRebuilds(int &$rebuilds, ?Closure $during = null): void
{
    DB::listen(static function ($query) use (&$rebuilds, $during): void {
        if (str_starts_with($query->sql, 'select "votes_count", "id" from "nominees"')) {
            $rebuilds++;
            $during?->__invoke();
        }
    });
}

it('rebuilds once when concurrent polls find no copy at all', function (): void {
    $nominee = Nominee::factory()->create(['votes_count' => 12]);
    $categoryId = $nominee->award_category_id;
    $category = $nominee->awardCategory;
    $rebuilds = 0;
    countRebuilds($rebuilds);

    // The first poll misses; before it takes the rebuild lock, a second poll misses too and wins the lock.
    $interleaved = false;
    $second = null;
    Event::listen(CacheMissed::class, static function (CacheMissed $event) use (
        &$interleaved,
        &$second,
        $categoryId,
        $category,
    ): void {
        if ($interleaved || $event->key !== VoteKeys::leaderboard($categoryId)) {
            return;
        }

        $interleaved = true;
        $second = LeaderboardCache::standings($category);
    });

    $first = LeaderboardCache::standings($category);

    expect($interleaved)
        ->toBeTrue()
        ->and($rebuilds)
        ->toBe(1)
        ->and($first)
        ->toBe([$nominee->id => 12])
        ->and($second)
        ->toBe($first);
});

it('serves the stale copy to concurrent polls while one of them rebuilds', function (): void {
    $nominee = Nominee::factory()->create(['votes_count' => 5]);
    $categoryId = $nominee->award_category_id;
    $category = $nominee->awardCategory;
    LeaderboardCache::standings($category);
    Nominee::query()->whereKey($nominee->id)->update(['votes_count' => 9]);
    LeaderboardCache::forget($categoryId);

    // While the first poll holds the lock and runs its rebuild query, a second poll arrives.
    $rebuilds = 0;
    $second = null;
    countRebuilds($rebuilds, static function () use (&$second, $category): void {
        $second ??= LeaderboardCache::standings($category);
    });

    $first = LeaderboardCache::standings($category);

    expect($rebuilds)
        ->toBe(1)
        ->and($first)
        ->toBe([$nominee->id => 9])
        ->and($second)
        ->toBe([$nominee->id => 5])
        ->and(LeaderboardCache::standings($category))
        ->toBe([$nominee->id => 9]);
});

it('serves the stale copy without waiting or querying when the rebuild lock is stuck', function (): void {
    $nominee = Nominee::factory()->create(['votes_count' => 4]);
    $categoryId = $nominee->award_category_id;
    $category = $nominee->awardCategory;
    LeaderboardCache::standings($category);
    Nominee::query()->whereKey($nominee->id)->update(['votes_count' => 6]);
    LeaderboardCache::forget($categoryId);
    Cache::lock(VoteKeys::leaderboardRebuildLock($categoryId), 30)->get();

    $rebuilds = 0;
    countRebuilds($rebuilds);
    $started = microtime(true);

    expect(LeaderboardCache::standings($category))
        ->toBe([$nominee->id => 4])
        ->and($rebuilds)
        ->toBe(0)
        ->and(microtime(true) - $started)
        ->toBeLessThan(1.0);
});

it('reads the counter directly only when no copy exists and the rebuild lock is stuck', function (): void {
    $nominee = Nominee::factory()->create(['votes_count' => 4]);
    Cache::lock(VoteKeys::leaderboardRebuildLock($nominee->award_category_id), 30)->get();

    expect(LeaderboardCache::standings($nominee->awardCategory))
        ->toBe([$nominee->id => 4])
        ->and(Cache::has(VoteKeys::leaderboard($nominee->award_category_id)))
        ->toBeFalse();
});

it('fails closed: returns nothing and reads no cache key for a hidden-results category', function (CategoryStatus $status): void {
    $category = AwardCategory::factory()->status($status)->create();
    $nominee = Nominee::factory()->for($category)->create(['votes_count' => 31]);

    // A copy holding counts already sits in the cache, e.g. from before the category went blind.
    LeaderboardCache::refresh($category->id);

    $read = [];
    Event::listen(RetrievingKey::class, static function (RetrievingKey $event) use (&$read): void {
        $read[] = $event->key;
    });
    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    expect(LeaderboardCache::standings($category))
        ->toBeNull()
        ->and($read)
        ->toBeEmpty()
        ->and($queries)
        ->toBe(['select "status" from "award_categories" where "award_categories"."id" = ? limit 1'])
        ->and(Cache::get(VoteKeys::leaderboard($category->id)))
        ->toBe([$nominee->id => 31]);
})->with([
    'draft' => CategoryStatus::Draft,
    'embargoed' => CategoryStatus::Embargoed,
    'closed' => CategoryStatus::Closed,
]);

it('serves standings for categories whose results are public', function (CategoryStatus $status): void {
    $category = AwardCategory::factory()->status($status)->create();
    $nominee = Nominee::factory()->for($category)->create(['votes_count' => 8]);

    expect(LeaderboardCache::standings($category))->toBe([$nominee->id => 8]);
})->with([
    'open' => CategoryStatus::Open,
    'revealed' => CategoryStatus::Revealed,
]);

it('reads the stored status rather than trusting a stale model the caller still holds', function (): void {
    $category = AwardCategory::factory()->status(CategoryStatus::Open)->create();
    Nominee::factory()->for($category)->create(['votes_count' => 77]);
    expect(LeaderboardCache::standings($category))->not->toBeNull();

    // Another request embargoes the category; this caller's copy still says "open".
    AwardCategory::query()->whereKey($category->id)->update(['status' => CategoryStatus::Embargoed]);
    expect($category->status)->toBe(CategoryStatus::Open);

    expect(LeaderboardCache::standings($category))->toBeNull();
});

it('treats a category that no longer exists as hidden', function (): void {
    $category = AwardCategory::factory()->create();
    AwardCategory::query()->whereKey($category->id)->delete();

    expect(LeaderboardCache::standings($category))->toBeNull();
});

it('leaves archived nominees off the public leaderboard', function (): void {
    $category = AwardCategory::factory()->status(CategoryStatus::Open)->create();
    $active = Nominee::factory()->for($category)->create(['votes_count' => 5]);
    Nominee::factory()->for($category)->create(['votes_count' => 999, 'archived_at' => now()]);

    expect(LeaderboardCache::standings($category))->toBe([$active->id => 5]);
});
