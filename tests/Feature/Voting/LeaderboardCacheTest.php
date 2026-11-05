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
