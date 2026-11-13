<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\TransactionStatus;
use App\Enums\VotingMode;
use App\Exceptions\Organizations\LastOwnerException;
use App\Exceptions\Voting\FreeVoteUnavailableException;
use App\Models\AwardCategory;
use App\Models\FreeVoteClaim;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use Tests\Concurrency\Race;

/*
 * Two requests at the same moment, on two real PostgreSQL connections (D-137, cleanup batch 4 P4). Each test
 * proves the second side really waited on a lock (pg_stat_activity) and that the outcome is still right.
 * Skipped on SQLite; required in the PostgreSQL CI job.
 */

// A closure, not Race::requirePostgres(...): Pest binds beforeEach callbacks to the test case.
// @mago-expect lint:prefer-first-class-callable
beforeEach(fn() => Race::requirePostgres());

/**
 * @return list<array<string, mixed>>
 */
function raceOutcomes(Tests\Concurrency\RaceProcess ...$sides): array
{
    return array_map(static fn(Tests\Concurrency\RaceProcess $side): array => $side->wait(), $sides);
}

it('credits a payment once when its webhook and callback arrive together', function (): void {
    $nominee = Nominee::factory()->for(AwardCategory::factory()->status(CategoryStatus::Open))->create();
    $order = VoteTransaction::factory()->for($nominee)->create(['quantity' => 7]);
    $args = ['reference' => $order->payment_reference, 'amount' => $order->gross_amount];

    $outcomes = Race::holdingRowLock('vote_transactions', $order->id, static function () use ($args): array {
        $webhook = Race::start('webhook', 'fulfil', $args + ['trigger' => 'webhook']);
        $callback = Race::start('callback', 'fulfil', $args + ['trigger' => 'callback']);
        Race::waitUntilBlocked(['webhook', 'callback']);

        return [$webhook, $callback];
    });
    $outcomes = raceOutcomes(...$outcomes);

    expect(array_column($outcomes, 'ok'))
        ->toBe([true, true])
        ->and(array_sum(array_column($outcomes, 'newly_credited')))
        ->toBe(1)
        ->and($order->fresh()?->status)
        ->toBe(TransactionStatus::Successful)
        ->and(VoteRecord::query()->where('vote_transaction_id', $order->id)->count())
        ->toBe(1)
        ->and($nominee->fresh()?->votes_count)
        ->toBe(7);
});

it('holds the free-vote cap when one voter votes twice at the same moment', function (): void {
    $category = AwardCategory::factory()
        ->mode(VotingMode::Free)
        ->status(CategoryStatus::Open)
        ->create([
            'free_vote_limit' => 1,
        ]);
    $nominee = Nominee::factory()->for($category)->create();
    $args = ['nominee_id' => $nominee->id, 'voter_key' => hash('sha256', 'same-voter')];

    $outcomes = Race::holdingRowLock('nominees', $nominee->id, static function () use ($args): array {
        $first = Race::start('vote-1', 'free-vote', $args);
        $second = Race::start('vote-2', 'free-vote', $args);
        Race::waitUntilBlocked(['vote-1', 'vote-2']); // both have counted 0 claims and wait to write

        return [$first, $second];
    });
    $outcomes = raceOutcomes(...$outcomes);

    expect(array_column($outcomes, 'ok'))
        ->toEqualCanonicalizing([true, false])
        ->and(collect($outcomes)->firstWhere('ok', false)['exception'] ?? null)
        ->toBe(FreeVoteUnavailableException::class)
        ->and(FreeVoteClaim::query()->count())
        ->toBe(1)
        ->and(VoteRecord::query()->count())
        ->toBe(1)
        ->and($nominee->fresh()?->votes_count)
        ->toBe(1);
});

it('gives the losing request the next free-vote slot when one is left (P5)', function (): void {
    $category = AwardCategory::factory()
        ->mode(VotingMode::Free)
        ->status(CategoryStatus::Open)
        ->create([
            'free_vote_limit' => 2,
        ]);
    $nominee = Nominee::factory()->for($category)->create();
    $args = ['nominee_id' => $nominee->id, 'voter_key' => hash('sha256', 'same-voter')];

    $outcomes = Race::holdingRowLock('nominees', $nominee->id, static function () use ($args): array {
        $first = Race::start('vote-1', 'free-vote', $args);
        $second = Race::start('vote-2', 'free-vote', $args);
        Race::waitUntilBlocked(['vote-1', 'vote-2']); // both counted 0 claims: both will try slot 1

        return [$first, $second];
    });
    $outcomes = raceOutcomes(...$outcomes);

    expect(array_column($outcomes, 'ok'))
        ->toBe([true, true])
        ->and(FreeVoteClaim::query()->orderBy('slot')->pluck('slot')->all())
        ->toBe([1, 2])
        ->and($nominee->fresh()?->votes_count)
        ->toBe(2);
});

it('never lets two owners remove or demote each other at the same moment', function (): void {
    $organization = Organization::factory()->create();
    $ama = organizer(OrganizationRole::Owner, $organization);
    $kofi = organizer(OrganizationRole::Owner, $organization);

    $outcomes = Race::holdingRowLock('organizations', $organization->id, static function () use (
        $organization,
        $ama,
        $kofi,
    ): array {
        $demote = Race::start('demote-kofi', 'change-role', [
            'organization_id' => $organization->id,
            'member_id' => $kofi->id,
            'role' => OrganizationRole::Admin->value,
            'actor_id' => $ama->id,
        ]);
        $remove = Race::start('remove-ama', 'remove-member', [
            'organization_id' => $organization->id,
            'member_id' => $ama->id,
            'actor_id' => $kofi->id,
        ]);
        Race::waitUntilBlocked(['demote-kofi', 'remove-ama']);

        return [$demote, $remove];
    });
    $outcomes = raceOutcomes(...$outcomes);

    expect(array_column($outcomes, 'ok'))
        ->toEqualCanonicalizing([true, false])
        ->and(collect($outcomes)->firstWhere('ok', false)['exception'] ?? null)
        ->toBe(LastOwnerException::class)
        ->and($organization->fresh()?->ownerCount())
        ->toBe(1);
});

it('loses no vote when a payment is credited while a recount runs, and reports no false drift', function (): void {
    $category = AwardCategory::factory()->status(CategoryStatus::Open)->create();
    $nominee = Nominee::factory()->for($category)->create();
    withLedgerVotes($nominee, 40);
    $order = VoteTransaction::factory()->for($nominee)->create(['quantity' => 5]);
    $dir = sys_get_temp_dir() . '/votelab-race-' . bin2hex(random_bytes(6));
    mkdir($dir);

    try {
        // The payment pauses inside its transaction, its ledger row written but not committed and the counter not
        // yet incremented: the moment a recount could miscount.
        $credit = Race::start('credit', 'fulfil', [
            'reference' => $order->payment_reference,
            'amount' => $order->gross_amount,
            'trigger' => 'webhook',
            'pause_dir' => $dir,
        ]);
        Race::waitForFile("{$dir}/paused");

        $recount = Race::start('recount', 'recount', ['award_category_id' => $category->id]);
        Race::waitUntilBlocked(['recount']); // the recount waits for the payment's lock on the nominee row
        touch("{$dir}/go");

        [$credited, $recounted] = raceOutcomes($credit, $recount);
    } finally {
        foreach (['paused', 'go'] as $file) {
            if (file_exists("{$dir}/{$file}")) {
                unlink("{$dir}/{$file}");
            }
        }

        rmdir($dir);
    }

    $ledger = (int) VoteRecord::query()->where('nominee_id', $nominee->id)->sum('quantity');

    expect($credited)
        ->toBe(['ok' => true, 'newly_credited' => true])
        ->and($recounted)
        ->toBe(['ok' => true, 'drift' => []])
        ->and($ledger)
        ->toBe(45)
        ->and($nominee->fresh()?->votes_count)
        ->toBe(45);
});
