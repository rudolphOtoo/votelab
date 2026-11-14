<?php

declare(strict_types=1);

use App\Actions\Admin\RecountCategoryTallies;
use App\Actions\Voting\CastFreeVoteAction;
use App\Actions\Voting\FulfillPaidVoteOrderAction;
use App\Enums\CategoryStatus;
use App\Enums\PaymentTrigger;
use App\Enums\TransactionStatus;
use App\Enums\VotingMode;
use App\Models\AuditEvent;
use App\Models\AwardCategory;
use App\Models\FreeVoteClaim;
use App\Models\Nominee;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use App\Services\Payment\Data\GatewayPaymentStatus;
use App\Services\Security\VoterIdentity;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;

/*
 * The log entries operators rely on (README "Monitoring and recovery"): the payments log for every payment
 * decision, the voting log for free votes and drift, and the tally.drift alert and audit entry. Each event's name,
 * level and exact context are pinned here, so a refactor can't silently drop the ids someone searches by.
 * Surviving mutants in the money classes showed none of this was tested (D-145).
 */

/**
 * @return array{level: string, context: array<string, mixed>}
 */
function logged(TestHandler $handler, string $message): array
{
    $record = collect($handler->getRecords())->first(static fn(LogRecord $r): bool => $r->message === $message);
    expect($record)->not->toBeNull("expected a {$message} log entry");

    return ['level' => $record->level->getName(), 'context' => $record->context];
}

function paidOrder(int $quantity = 3): VoteTransaction
{
    return VoteTransaction::factory()->for(Nominee::factory()->for(AwardCategory::factory()->status(
        CategoryStatus::Open,
    )))->create(['quantity' => $quantity]);
}

function fulfilVia(VoteTransaction $order, PaymentTrigger $trigger = PaymentTrigger::Webhook): void
{
    app(FulfillPaidVoteOrderAction::class)->execute($order->payment_reference, $trigger);
}

/**
 * @return array<string, int|string>
 */
function orderIds(VoteTransaction $order, string $trigger = 'webhook'): array
{
    return [
        'reference' => $order->payment_reference,
        'trigger' => $trigger,
        'vote_transaction_id' => $order->id,
        'award_program_id' => $order->award_program_id,
        'award_category_id' => $order->award_category_id,
    ];
}

/**
 * @return array<string, int|string>
 */
function verifiedAs(VoteTransaction $order, int $amount, string $status = 'success'): array
{
    return [
        'provider_status' => $status,
        'expected_amount' => $order->gross_amount,
        'verified_amount' => $amount,
        'verified_currency' => 'GHS',
    ];
}

beforeEach(function (): void {
    $this->gateway = fakeGateway();
    $this->payments = captureLog('payments');
});

it('logs a credit with every id, the verified amounts and what was credited', function (): void {
    $order = paidOrder(quantity: 3);
    $this->gateway->reports($order->payment_reference, amount: $order->gross_amount);

    fulfilVia($order, PaymentTrigger::Callback);

    expect(logged($this->payments, 'fulfilment.credited'))->toBe([
        'level' => 'INFO',
        'context' =>
            orderIds($order, 'callback')
                + verifiedAs($order, $order->gross_amount)
                + [
                    'nominee_id' => $order->nominee_id,
                    'quantity' => 3,
                    'gross_amount' => $order->gross_amount,
                ],
    ]);
});

it('logs an already settled payment with its status, and an unknown reference with what it was given', function (): void {
    $order = paidOrder();
    $order->forceFill(['status' => TransactionStatus::Successful])->save();

    fulfilVia($order);
    app(FulfillPaidVoteOrderAction::class)->execute('VL_NOT_OURS', PaymentTrigger::Sweeper);

    expect(logged($this->payments, 'fulfilment.already_settled'))
        ->toBe(['level' => 'INFO', 'context' => orderIds($order) + ['status' => 'successful']])
        ->and(logged($this->payments, 'fulfilment.unknown_reference'))
        ->toBe(['level' => 'WARNING', 'context' => ['reference' => 'VL_NOT_OURS', 'trigger' => 'sweeper']]);
});

it('logs a failed payment at info and a flagged one at warning, with the reason and whether it applied', function (): void {
    $failed = paidOrder();
    $this->gateway->reports($failed->payment_reference, GatewayPaymentStatus::Failed, $failed->gross_amount);
    $flagged = paidOrder();
    $this->gateway->reports($flagged->payment_reference, amount: $flagged->gross_amount + 1);

    fulfilVia($failed);
    fulfilVia($flagged);

    expect(logged($this->payments, 'fulfilment.failed'))
        ->toBe([
            'level' => 'INFO',
            'context' =>
                orderIds($failed)
                    + verifiedAs($failed, $failed->gross_amount, 'failed')
                    + [
                        'reason' => 'payment_failed',
                        'applied' => true,
                    ],
        ])
        ->and(logged($this->payments, 'fulfilment.flagged'))
        ->toBe([
            'level' => 'WARNING',
            'context' =>
                orderIds($flagged)
                    + verifiedAs($flagged, $flagged->gross_amount + 1)
                    + [
                        'reason' => 'amount_mismatch',
                        'applied' => true,
                    ],
        ]);
});

it('logs a payment still processing under its decision reason', function (): void {
    $order = paidOrder();
    $this->gateway->reports($order->payment_reference, GatewayPaymentStatus::Pending, $order->gross_amount);

    fulfilVia($order);

    expect(logged($this->payments, 'fulfilment.still_pending'))->toBe([
        'level' => 'INFO',
        'context' => orderIds($order) + verifiedAs($order, $order->gross_amount, 'pending'),
    ]);
});

it('logs a lost race with the status the other trigger left', function (): void {
    $order = paidOrder();
    $this->gateway->reports($order->payment_reference, amount: $order->gross_amount);
    $this->gateway->beforeVerifyReturns(static function () use ($order): void {
        VoteTransaction::query()->whereKey($order->id)->update(['status' => TransactionStatus::Failed]);
    });

    fulfilVia($order);

    expect(logged($this->payments, 'fulfilment.lost_race'))->toBe([
        'level' => 'INFO',
        'context' => orderIds($order) + verifiedAs($order, $order->gross_amount) + ['status' => 'failed'],
    ]);
});

it('logs free votes cast, refused and conflicting with the keyed voter and network hashes', function (): void {
    $voting = captureLog('voting');
    $category = AwardCategory::factory()
        ->mode(VotingMode::Free)
        ->status(CategoryStatus::Open)
        ->create([
            'free_vote_limit' => 1,
        ]);
    $nominee = Nominee::factory()->for($category)->create();
    $voter = new VoterIdentity(voterKey: hash('sha256', 'voter'), ipHash: hash('sha256', 'network'));
    $ids = [
        'award_program_id' => $nominee->award_program_id,
        'award_category_id' => $nominee->award_category_id,
        'nominee_id' => $nominee->id,
        'voter_key' => $voter->voterKey,
        'ip_hash' => $voter->ipHash,
    ];

    // One slot conflict (a competing claim inside the transaction), then the vote, then a refusal.
    $conflicts = 1;
    FreeVoteClaim::creating(static function (FreeVoteClaim $claim) use ($nominee, &$conflicts): void {
        if ($conflicts-- > 0) {
            $competitor = $claim->replicate();
            $competitor->vote_record_id = VoteRecord::factory()->for($nominee)->create()->id;
            $competitor->saveQuietly();
        }
    });
    $record = app(CastFreeVoteAction::class)->execute($nominee, $voter);
    rescue(static fn() => app(CastFreeVoteAction::class)->execute($nominee, $voter), report: false);

    expect(logged($voting, 'free_vote.slot_conflict'))
        ->toBe(['level' => 'INFO', 'context' => $ids + ['attempt' => 1]])
        ->and(logged($voting, 'free_vote.cast'))
        ->toBe(['level' => 'INFO', 'context' => $ids + ['vote_record_id' => $record->id]])
        ->and(logged($voting, 'free_vote.rejected'))
        ->toBe(['level' => 'WARNING', 'context' => $ids + ['reason' => 'limit_reached', 'limit' => 1]]);
});

it('alerts, logs and audits tally drift with the counter, the ledger and whether it was fixed', function (bool $dryRun): void {
    $alerts = captureLog('alerts');
    $voting = captureLog('voting');
    $nominee = withLedgerVotes(Nominee::factory()->create(), 7);
    $nominee->forceFill(['votes_count' => 9])->save();
    $drift = [
        'award_category_id' => $nominee->award_category_id,
        'nominee_id' => $nominee->id,
        'counter' => 9,
        'ledger' => 7,
        'fixed' => !$dryRun,
    ];

    RecountCategoryTallies::apply($nominee->award_category_id, dryRun: $dryRun);

    $audit = AuditEvent::query()->sole();
    expect(logged($alerts, 'tally.drift'))
        ->toBe(['level' => 'ERROR', 'context' => $drift])
        ->and(logged($voting, 'tally.drift'))
        ->toBe(['level' => 'ERROR', 'context' => $drift])
        ->and($audit->action)
        ->toBe($dryRun ? 'tally.drift_found' : 'tally.drift_corrected')
        // toEqual: on PostgreSQL the JSON columns are jsonb, which doesn't keep key order (D-140).
        ->and([$audit->before, $audit->after, $audit->context])
        ->toEqual([
            ['votes_count' => 9],
            ['votes_count' => 7],
            ['award_category_id' => $nominee->award_category_id, 'dry_run' => $dryRun],
        ])
        ->and($nominee->fresh()?->votes_count)
        ->toBe($dryRun ? 9 : 7);
})->with(['dry run' => true, 'fix' => false]);
