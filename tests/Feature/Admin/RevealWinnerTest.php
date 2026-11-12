<?php

declare(strict_types=1);

use App\Actions\Admin\RevealWinnerAction;
use App\Actions\Voting\FulfillPaidVoteOrderAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\PaymentTrigger;
use App\Enums\TransactionStatus;
use App\Events\WinnerRevealed;
use App\Exceptions\Admin\PendingPaymentsException;
use App\Models\AwardCategory;
use App\Models\CategoryStatusTransition;
use App\Models\Nominee;
use App\Models\User;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use App\Support\LeaderboardCache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

/**
 * A closed category (closed at 20:00) with nominees whose ledger votes are given, and an admin.
 *
 * @param  list<int>  $ledgerVotes
 * @return array{AwardCategory, list<Nominee>, User}
 */
function closedCategory(array $ledgerVotes = [10, 25, 5]): array
{
    Carbon::setTestNow('2026-11-20 20:00:00');
    $category = AwardCategory::factory()->status(CategoryStatus::Closed)->create(['closed_at' => now()]);
    $nominees = [];

    foreach ($ledgerVotes as $i => $votes) {
        $nominee = Nominee::factory()->for($category)->create(['votes_count' => $votes, 'display_order' => $i]);
        if ($votes > 0) {
            VoteRecord::factory()->for($nominee)->create(['quantity' => $votes, 'is_paid' => false]);
        }
        $nominees[] = $nominee;
    }

    return [$category, $nominees, organizer(OrganizationRole::Admin, $category->organization)];
}

function reveal(AwardCategory $category, User $actor, bool $override = false): App\Actions\Admin\RevealResult
{
    return app(RevealWinnerAction::class)->execute($category, $actor, $override);
}

it('reveals a closed category with its podium and winner', function (): void {
    Event::fake([WinnerRevealed::class]);
    [$category, $nominees, $admin] = closedCategory([10, 25, 5, 1]);

    $result = reveal($category, $admin);

    expect($category->fresh()?->status)
        ->toBe(CategoryStatus::Revealed)
        ->and($result->winnerIds)
        ->toBe([$nominees[1]->id])
        ->and(array_column($result->podium, 'votes'))
        ->toBe([25, 10, 5])
        ->and($result->newlyRevealed)
        ->toBeTrue();
    Event::assertDispatched(WinnerRevealed::class);
    expect(CategoryStatusTransition::query()->sole()->to_status)->toBe(CategoryStatus::Revealed);
});

it('names every tied nominee as a winner', function (): void {
    [$category, $nominees, $admin] = closedCategory([12, 12, 3]);

    expect(reveal($category, $admin)->winnerIds)->toBe([$nominees[0]->id, $nominees[1]->id]);
});

it('recounts from the ledger before revealing and serves the recount', function (): void {
    [$category, $nominees, $admin] = closedCategory([10, 25]);
    // The derived counter drifted (e.g. a lost update); the ledger says 10 and 25.
    Nominee::query()->whereKey($nominees[0]->id)->update(['votes_count' => 99]);

    $result = reveal($category, $admin);

    expect($nominees[0]->fresh()?->votes_count)
        ->toBe(10)
        ->and($result->winnerIds)
        ->toBe([$nominees[1]->id])
        ->and(LeaderboardCache::standings($category->fresh() ?? $category))
        ->toBe([$nominees[0]->id => 10, $nominees[1]->id => 25]);
});

it('is idempotent: a second reveal changes nothing and returns the same podium', function (): void {
    [$category, , $admin] = closedCategory();
    $first = reveal($category, $admin);
    Event::fake([WinnerRevealed::class]);

    $second = reveal($category, $admin);

    expect($second->podium)
        ->toBe($first->podium)
        ->and($second->newlyRevealed)
        ->toBeFalse()
        ->and(CategoryStatusTransition::query()->count())
        ->toBe(1);
    Event::assertNotDispatched(WinnerRevealed::class);
});

it('only reveals a closed category', function (CategoryStatus $status): void {
    $category = AwardCategory::factory()->status($status)->create();
    $owner = organizer(OrganizationRole::Owner, $category->organization);

    expect(fn() => reveal($category, $owner))->toThrow(AuthorizationException::class);
    expect($category->fresh()?->status)->toBe($status);
})->with([CategoryStatus::Draft, CategoryStatus::Open, CategoryStatus::Embargoed]);

it('refuses staff and outsiders', function (): void {
    [$category] = closedCategory();

    expect(fn() => reveal($category, organizer(OrganizationRole::Staff, $category->organization)))
        ->toThrow(AuthorizationException::class)
        ->and(fn() => reveal($category, organizer()))
        ->toThrow(AuthorizationException::class);
});

it('refuses while payments started before the close are still pending, and says how many', function (): void {
    config(['voting.close_grace_minutes' => 30]);
    [$category, $nominees, $admin] = closedCategory();
    VoteTransaction::factory()
        ->for($nominees[0])
        ->count(2)
        ->create(['created_at' => now()->subMinutes(2)]);
    VoteTransaction::factory()->for($nominees[0])->create(['created_at' => now()->addMinute()]); // after the close

    expect(RevealWinnerAction::pendingPaymentsCount($category))->toBe(2);

    try {
        reveal($category, $admin);
        $this->fail('Expected PendingPaymentsException.');
    } catch (PendingPaymentsException $e) {
        expect($e->pending)->toBe(2);
    }

    expect($category->fresh()?->status)->toBe(CategoryStatus::Closed);
});

it('reveals with an explicit override; those payments are refunded, not counted, when confirmed', function (): void {
    config(['voting.close_grace_minutes' => 30]);
    $gateway = fakeGateway();
    [$category, $nominees, $admin] = closedCategory([10, 25]);
    $late = VoteTransaction::factory()->for($nominees[0])->create([
        'quantity' => 50,
        'created_at' => now()->subMinute(),
    ]);
    $gateway->reports($late->payment_reference, amount: $late->gross_amount);

    $result = reveal($category, $admin, override: true);
    Carbon::setTestNow(now()->addMinutes(2)); // Inside the 30-minute cap, but after the reveal.
    app(FulfillPaidVoteOrderAction::class)->execute($late->payment_reference, PaymentTrigger::Webhook);

    expect($result->pendingPaymentsOverridden)
        ->toBe(1)
        ->and($late->fresh()?->status)
        ->toBe(TransactionStatus::PaidUncredited)
        ->and($nominees[0]->fresh()?->votes_count)
        ->toBe(10)
        ->and(CategoryStatusTransition::query()->sole()->reason)
        ->toBe('revealed_with_1_pending_payments');
});

it('audits the reveal with the winners', function (): void {
    $audit = captureAuditLog();
    [$category, , $admin] = closedCategory();

    reveal($category, $admin);

    expect($audit->hasInfoThatContains('category.revealed'))->toBeTrue();
});

it('does not let an abandoned checkout block the reveal', function (): void {
    config(['voting.close_grace_minutes' => 30]);
    [$category, $nominees, $admin] = closedCategory(); // closed at 20:00
    // Started 40 minutes ago and never finished: abandoned, even though the close was only minutes ago.
    Carbon::setTestNow('2026-11-20 20:05:00');
    VoteTransaction::factory()->for($nominees[0])->create(['created_at' => now()->subMinutes(40)]);

    expect(RevealWinnerAction::pendingPaymentsCount($category))
        ->toBe(0)
        ->and(reveal($category, $admin)->newlyRevealed)
        ->toBeTrue();
});

it('stops blocking once the 30-minute cap after the close has passed', function (): void {
    config(['voting.close_grace_minutes' => 30]);
    [$category, $nominees, $admin] = closedCategory(); // closed at 20:00
    VoteTransaction::factory()->for($nominees[0])->create(['created_at' => now()->subMinute()]);
    expect(RevealWinnerAction::pendingPaymentsCount($category))->toBe(1);

    Carbon::setTestNow('2026-11-20 20:30:00');

    expect(RevealWinnerAction::pendingPaymentsCount($category))
        ->toBe(0)
        ->and(reveal($category, $admin)->newlyRevealed)
        ->toBeTrue();
});

it('does not count checkouts the sweeper already expired', function (): void {
    config(['voting.close_grace_minutes' => 30]);
    [$category, $nominees] = closedCategory();
    VoteTransaction::factory()->for($nominees[0])->create([
        'created_at' => now()->subMinute(),
        'status' => TransactionStatus::Expired,
    ]);

    expect(RevealWinnerAction::pendingPaymentsCount($category))->toBe(0);
});

it('alerts on drift: an error-level log and one audit event per nominee, before and after', function (): void {
    $voting = captureLog('voting');
    [$category, $nominees, $admin] = closedCategory([10, 25, 5]);
    Nominee::query()->whereKey($nominees[0]->id)->update(['votes_count' => 99]);
    Nominee::query()->whereKey($nominees[2]->id)->update(['votes_count' => 4]);

    reveal($category, $admin);

    expect($voting->hasErrorThatContains('tally.drift'))
        ->toBeTrue()
        ->and(collect($voting->getRecords())->where('message', 'tally.drift')->count())
        ->toBe(2);

    $events = App\Models\AuditEvent::query()->where('action', 'tally.drift_corrected')->orderBy('subject_id')->get();
    expect($events)
        ->toHaveCount(2)
        ->and($events->pluck('subject_id')->all())
        ->toBe([$nominees[0]->id, $nominees[2]->id])
        ->and($events[0]->before)
        ->toBe(['votes_count' => 99])
        ->and($events[0]->after)
        ->toBe(['votes_count' => 10])
        ->and($events[0]->actor_user_id)
        ->toBe($admin->id)
        ->and($events[0]->organization_id)
        ->toBe($category->organization_id);
});

it('raises no drift alert when the counters match the ledger', function (): void {
    $voting = captureLog('voting');
    [$category, , $admin] = closedCategory();

    reveal($category, $admin);

    expect($voting->hasErrorRecords())
        ->toBeFalse()
        ->and(App\Models\AuditEvent::query()->where('action', 'like', 'tally.%')->count())
        ->toBe(0);
});

it('reports drift without fixing it on a dry run (for votes:rebuild-tallies)', function (): void {
    [$category, $nominees] = closedCategory([10, 25]);
    Nominee::query()->whereKey($nominees[1]->id)->update(['votes_count' => 30]);

    $drift = App\Actions\Admin\RecountCategoryTallies::apply($category->id, dryRun: true);

    expect($drift)
        ->toBe([$nominees[1]->id => ['counter' => 30, 'ledger' => 25]])
        ->and($nominees[1]->fresh()?->votes_count)
        ->toBe(30)
        ->and(App\Models\AuditEvent::query()->where('action', 'tally.drift_found')->count())
        ->toBe(1);
});
