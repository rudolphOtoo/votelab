<?php

declare(strict_types=1);

use App\Actions\Voting\CastFreeVoteAction;
use App\Actions\Voting\FulfillPaidVoteOrderAction;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(fn() => config(['voting.leaderboard_cache_seconds' => 3_600]));

it('registers the refresh listener for paid and free votes', function (): void {
    Event::fake();

    Event::assertListening(VotesCredited::class, [RefreshLeaderboardCache::class, 'handleVotesCredited']);
    Event::assertListening(FreeVoteCast::class, [RefreshLeaderboardCache::class, 'handleFreeVoteCast']);
});

it('serves polls from the cache without querying again', function (): void {
    $nominee = Nominee::factory()->create();
    LeaderboardCache::standings($nominee->award_category_id);

    $queries = 0;
    DB::listen(static function () use (&$queries): void {
        $queries++;
    });
    LeaderboardCache::standings($nominee->award_category_id);

    expect($queries)->toBe(0);
});

it('refreshes cached standings as soon as paid votes are credited', function (): void {
    $gateway = fakeGateway();
    $transaction = VoteTransaction::factory()->create(['quantity' => 40]);
    $gateway->reports($transaction->payment_reference, amount: $transaction->gross_amount);

    expect(LeaderboardCache::standings($transaction->award_category_id)[$transaction->nominee_id])->toBe(0);

    app(FulfillPaidVoteOrderAction::class)->execute($transaction->payment_reference, PaymentTrigger::Webhook);

    expect(LeaderboardCache::standings($transaction->award_category_id)[$transaction->nominee_id])->toBe(40);
});

it('refreshes cached standings as soon as a free vote is cast', function (): void {
    $nominee = Nominee::factory()->for(AwardCategory::factory()->mode(VotingMode::Free))->create();
    expect(LeaderboardCache::standings($nominee->award_category_id)[$nominee->id])->toBe(0);

    app(CastFreeVoteAction::class)->execute($nominee, new VoterIdentity(str_repeat('a', 64), str_repeat('b', 64)));

    expect(LeaderboardCache::standings($nominee->award_category_id)[$nominee->id])->toBe(1);
});

it('invalidates instead of rebuilding when votes land', function (): void {
    $nominee = Nominee::factory()->for(AwardCategory::factory()->mode(VotingMode::Free))->create();
    LeaderboardCache::standings($nominee->award_category_id);

    app(CastFreeVoteAction::class)->execute($nominee, new VoterIdentity(str_repeat('a', 64), str_repeat('b', 64)));

    expect(Cache::has(VoteKeys::leaderboard($nominee->award_category_id)))->toBeFalse();
});

it('rebuilds once when concurrent polls miss the cache together', function (): void {
    $nominee = Nominee::factory()->create(['votes_count' => 12]);
    $categoryId = $nominee->award_category_id;
    $rebuilds = 0;
    DB::listen(static function ($query) use (&$rebuilds): void {
        if (str_starts_with($query->sql, 'select "votes_count", "id" from "nominees"')) {
            $rebuilds++;
        }
    });

    // The first poll misses; before it takes the rebuild lock, a second poll misses too and wins the lock.
    $interleaved = false;
    $second = null;
    Event::listen(CacheMissed::class, static function (CacheMissed $event) use (
        &$interleaved,
        &$second,
        $categoryId,
    ): void {
        if ($interleaved || $event->key !== VoteKeys::leaderboard($categoryId)) {
            return;
        }

        $interleaved = true;
        $second = LeaderboardCache::standings($categoryId);
    });

    $first = LeaderboardCache::standings($categoryId);

    expect($interleaved)
        ->toBeTrue()
        ->and($rebuilds)
        ->toBe(1)
        ->and($first)
        ->toBe([$nominee->id => 12])
        ->and($second)
        ->toBe($first);
});

it('reads the counter directly rather than failing when a rebuild lock is stuck', function (): void {
    $nominee = Nominee::factory()->create(['votes_count' => 4]);
    Cache::lock(VoteKeys::leaderboardRebuildLock($nominee->award_category_id), 30)->get();

    expect(LeaderboardCache::standings($nominee->award_category_id))
        ->toBe([$nominee->id => 4])
        ->and(Cache::has(VoteKeys::leaderboard($nominee->award_category_id)))
        ->toBeFalse();
});
