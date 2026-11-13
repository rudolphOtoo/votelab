<?php

declare(strict_types=1);

use App\Actions\Voting\CastFreeVoteAction;
use App\Enums\CategoryStatus;
use App\Enums\VotingMode;
use App\Events\FreeVoteCast;
use App\Exceptions\Voting\FreeVoteUnavailableException;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\FreeVoteClaim;
use App\Models\Nominee;
use App\Models\VoteRecord;
use App\Services\Security\VoterIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

function freeNominee(
    VotingMode $mode = VotingMode::Free,
    int $limit = 1,
    CategoryStatus $status = CategoryStatus::Open,
): Nominee {
    $category = AwardCategory::factory()->mode($mode)->status($status)->create(['free_vote_limit' => $limit]);

    return Nominee::factory()->for($category)->create();
}

function voter(string $who = 'ama'): VoterIdentity
{
    return new VoterIdentity(voterKey: hash('sha256', "voter-{$who}"), ipHash: hash('sha256', 'campus-nat'));
}

function castFree(Nominee $nominee, VoterIdentity $voter): VoteRecord
{
    return app(CastFreeVoteAction::class)->execute($nominee, $voter);
}

beforeEach(fn() => Carbon::setTestNow('2026-11-05 10:00:00'));

it('records a free vote in the ledger and the counter', function (): void {
    Event::fake([FreeVoteCast::class]);
    $nominee = freeNominee();

    $record = castFree($nominee, voter());

    expect($record->is_paid)
        ->toBeFalse()
        ->and($record->quantity)
        ->toBe(1)
        ->and($record->vote_transaction_id)
        ->toBeNull()
        ->and($record->voter_fingerprint)
        ->toBe(voter()->voterKey)
        ->and($record->ip_hash)
        ->toBe(voter()->ipHash)
        ->and($nominee->fresh()?->votes_count)
        ->toBe(1)
        ->and(FreeVoteClaim::query()->sole()->only(['slot', 'period']))
        ->toBe(['slot' => 1, 'period' => '2026-11-05']);

    Event::assertDispatched(FreeVoteCast::class);
});

it('allows one free vote per voter per category per day, then refuses', function (): void {
    $nominee = freeNominee();
    castFree($nominee, voter());

    expect(fn() => castFree($nominee, voter()))
        ->toThrow(FreeVoteUnavailableException::class, "already used today's free vote");

    expect($nominee->fresh()?->votes_count)->toBe(1)->and(VoteRecord::query()->count())->toBe(1);
});

it('counts the allowance per category, not per nominee', function (): void {
    $nominee = freeNominee();
    $rival = Nominee::factory()->for($nominee->awardCategory)->create();
    castFree($nominee, voter());

    expect(fn() => castFree($rival, voter()))->toThrow(FreeVoteUnavailableException::class);
});

it('lets other voters, other categories and the next day through', function (): void {
    $nominee = freeNominee();
    castFree($nominee, voter('ama'));

    castFree($nominee, voter('kofi'));
    castFree(freeNominee(), voter('ama'));
    Carbon::setTestNow('2026-11-06 00:00:01');
    castFree($nominee, voter('ama'));

    expect(VoteRecord::query()->count())->toBe(4)->and($nominee->fresh()?->votes_count)->toBe(3);
});

it('honours a category allowance above one, slot by slot', function (): void {
    $nominee = freeNominee(limit: 3);

    foreach (range(1, 3) as $ignored) {
        castFree($nominee, voter());
    }

    expect(fn() => castFree($nominee, voter()))->toThrow(FreeVoteUnavailableException::class, 'all 3');
    expect(FreeVoteClaim::query()->orderBy('slot')->pluck('slot')->all())->toBe([1, 2, 3]);
});

it('enforces the cap in the database even if the PHP check is bypassed', function (): void {
    $nominee = freeNominee();
    $record = castFree($nominee, voter());

    FreeVoteClaim::query()->create([
        'award_category_id' => $nominee->award_category_id,
        'vote_record_id' => VoteRecord::factory()->for($nominee)->create()->id,
        'voter_key' => voter()->voterKey,
        'period' => '2026-11-05',
        'slot' => 1,
    ]);
})->throws(QueryException::class);

it('accepts free votes in hybrid categories and during blind voting', function (): void {
    castFree(freeNominee(VotingMode::Hybrid), voter());
    castFree(freeNominee(status: CategoryStatus::Embargoed), voter());

    expect(VoteRecord::query()->count())->toBe(2);
});

it('refuses free votes where they are not allowed', function (Closure $nominee, string $message): void {
    expect(fn() => castFree($nominee(), voter()))->toThrow(FreeVoteUnavailableException::class, $message);
    expect(VoteRecord::query()->count())->toBe(0);
})->with([
    'paid-only category' => [fn() => freeNominee(VotingMode::Paid), 'does not accept free votes'],
    'closed category' => [fn() => freeNominee(status: CategoryStatus::Closed), 'not open'],
    'draft category' => [fn() => freeNominee(status: CategoryStatus::Draft), 'not open'],
    'unpublished program' => [
        fn() => Nominee::factory()->for(AwardCategory::factory()->for(AwardProgram::factory()->draft())->mode(
            VotingMode::Free,
        ))->create(),
        'not open',
    ],
]);

it('keeps free and paid votes distinguishable in the ledger', function (): void {
    $nominee = freeNominee(VotingMode::Hybrid);
    castFree($nominee, voter());
    VoteRecord::factory()->for($nominee)->create(['is_paid' => true, 'quantity' => 10, 'voter_fingerprint' => null]);

    expect((int) VoteRecord::query()->where('is_paid', false)->sum('quantity'))
        ->toBe(1)
        ->and((int) VoteRecord::query()->where('is_paid', true)->sum('quantity'))
        ->toBe(10);
});

/**
 * Makes the next $times claim inserts meet a competing claim for the same slot, as if a concurrent request by the
 * same voter got there first. The competitor is inserted inside the action's transaction, so the database raises
 * a real unique violation and the rollback removes the competitor too. tests/Concurrency proves the real race.
 */
function competingClaims(Nominee $nominee, int $times): void
{
    FreeVoteClaim::creating(static function (FreeVoteClaim $claim) use ($nominee, &$times): void {
        if ($times-- <= 0 || $claim->vote_record_id === null) {
            return;
        }

        $competitor = $claim->replicate();
        $competitor->vote_record_id = VoteRecord::factory()->for($nominee)->create()->id;
        $competitor->saveQuietly();
    });
}

it('retries once with a fresh count when a concurrent request takes the same slot, then counts the vote', function (): void {
    $nominee = freeNominee(limit: 2);
    competingClaims($nominee, times: 1);

    $record = castFree($nominee, voter());

    expect($record->exists)
        ->toBeTrue()
        ->and(FreeVoteClaim::query()->where('vote_record_id', $record->id)->value('slot'))
        ->toBe(1)
        ->and(VoteRecord::query()->count())
        ->toBe(1)
        ->and($nominee->fresh()?->votes_count)
        ->toBe(1);
});

it('refuses after a second slot conflict instead of retrying for ever', function (): void {
    $nominee = freeNominee(limit: 2);
    competingClaims($nominee, times: 2);

    expect(fn() => castFree($nominee, voter()))->toThrow(FreeVoteUnavailableException::class);
    expect(VoteRecord::query()->count())->toBe(0)->and($nominee->fresh()?->votes_count)->toBe(0);
});
