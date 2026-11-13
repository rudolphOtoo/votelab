<?php

declare(strict_types=1);

use App\Actions\Reporting\RecordRefundAction;
use App\Enums\OrganizationRole;
use App\Enums\TransactionStatus;
use App\Models\AuditEvent;
use App\Models\RefundRecord;
use App\Models\VoteTransaction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function uncredited(): VoteTransaction
{
    return VoteTransaction::factory()->create([
        'status' => TransactionStatus::PaidUncredited,
        'status_reason' => 'category_closed',
    ]);
}

function recordRefund(
    VoteTransaction $transaction,
    App\Models\User $actor,
    ?string $reference = null,
    bool $confirmed = true,
): RefundRecord {
    return app(RecordRefundAction::class)->record($transaction, $actor, 'Refunded on MoMo', $reference, $confirmed);
}

it('lists only received-but-uncounted payments not yet refunded', function (): void {
    $waiting = uncredited();
    $program = $waiting->awardProgram;
    VoteTransaction::factory()->for($waiting->nominee)->create(['status' => TransactionStatus::Successful]);
    $owner = organizer(OrganizationRole::Owner, $program->organization);
    $done = VoteTransaction::factory()->for($waiting->nominee)->create(['status' => TransactionStatus::PaidUncredited]);
    recordRefund($done, $owner);

    expect(
        app(RecordRefundAction::class)
            ->queue($program, organizer(OrganizationRole::Admin, $program->organization))
            ->pluck('id')
            ->all(),
    )->toBe([$waiting->id]);
});

it('records a refund with an optional Paystack reference, once, and audits it', function (): void {
    $transaction = uncredited();
    $owner = organizer(OrganizationRole::Owner, $transaction->organization);

    $record = recordRefund($transaction, $owner, 'RFND_abc123');

    expect($record->paystack_refund_reference)
        ->toBe('RFND_abc123')
        ->and($record->recorded_by_user_id)
        ->toBe($owner->id)
        ->and($transaction->fresh()?->status)
        ->toBe(TransactionStatus::PaidUncredited)
        ->and(AuditEvent::query()->where('action', 'refund.recorded')->exists())
        ->toBeTrue()
        ->and(fn() => recordRefund($transaction, $owner))
        ->toThrow(ValidationException::class);
});

it('needs the owner, a confirmed password and an uncounted payment', function (): void {
    $transaction = uncredited();
    $owner = organizer(OrganizationRole::Owner, $transaction->organization);

    expect(fn() => recordRefund($transaction, organizer(OrganizationRole::Admin, $transaction->organization)))
        ->toThrow(AuthorizationException::class)
        ->and(fn() => recordRefund($transaction, organizer()))
        ->toThrow(AuthorizationException::class)
        ->and(fn() => recordRefund($transaction, $owner, confirmed: false))
        ->toThrow(ValidationException::class)
        ->and(fn() => recordRefund($transaction, $owner, 'bad ref!'))
        ->toThrow(ValidationException::class)
        ->and(fn() => recordRefund(VoteTransaction::factory()->for($transaction->nominee)->create([
            'status' => TransactionStatus::Successful,
        ]), $owner))
        ->toThrow(ValidationException::class);
    expect(RefundRecord::query()->count())->toBe(0);
});

it('keeps refund records append-only', function (): void {
    $transaction = uncredited();
    $record = recordRefund($transaction, organizer(OrganizationRole::Owner, $transaction->organization));

    expect(fn() => $record->forceFill(['reason' => 'x'])->save())
        ->toThrow(LogicException::class)
        ->and($record->delete(...))
        ->toThrow(LogicException::class);
});

it('refuses a duplicate refund without breaking the surrounding transaction (PostgreSQL savepoint)', function (): void {
    $transaction = uncredited();
    $owner = organizer(OrganizationRole::Owner, $transaction->organization);

    // The test already runs inside a transaction (RefreshDatabase), like a caller that wraps the action.
    recordRefund($transaction, $owner);
    expect(fn() => recordRefund($transaction, $owner))
        ->toThrow(ValidationException::class, 'This refund has already been recorded.');

    // On PostgreSQL a failed statement outside a savepoint aborts the whole transaction: this would fail.
    expect(RefundRecord::query()->where('vote_transaction_id', $transaction->id)->count())->toBe(1);
});
