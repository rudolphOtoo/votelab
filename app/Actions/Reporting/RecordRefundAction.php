<?php

declare(strict_types=1);

namespace App\Actions\Reporting;

use App\Enums\TransactionStatus;
use App\Models\AwardProgram;
use App\Models\RefundRecord;
use App\Models\User;
use App\Models\VoteTransaction;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The refund queue: payments that were received but not counted (paid_uncredited) and not yet recorded as
 * refunded. Recording a refund (refunds.record, owners only, with a confirmed password) notes that the
 * money was returned outside VoteLab — nothing is sent to Paystack and the transaction and vote records are
 * never changed (D-82). One record per transaction, enforced by a unique key. Audited.
 */
final readonly class RecordRefundAction
{
    /**
     * @return Collection<int, VoteTransaction>
     *
     * @throws AuthorizationException
     */
    public function queue(AwardProgram $program, User $actor): Collection
    {
        Gate::forUser($actor)->authorize('viewRefunds', $program);

        return VoteTransaction::query()
            ->where('award_program_id', $program->id)
            ->where('status', TransactionStatus::PaidUncredited)
            ->whereNotExists(
                static fn($query) => $query
                    ->selectRaw('1')
                    ->from('refund_records')
                    ->whereColumn('refund_records.vote_transaction_id', 'vote_transactions.id'),
            )
            ->with('nominee:id,name,stage_name')
            ->oldest('id')
            ->get();
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function record(
        VoteTransaction $transaction,
        User $actor,
        string $reason,
        ?string $paystackRefundReference,
        bool $passwordConfirmed,
    ): RefundRecord {
        $transaction = VoteTransaction::query()->with('awardProgram')->findOrFail($transaction->id);
        Gate::forUser($actor)->authorize('recordRefunds', $transaction->awardProgram);

        $data = Validator::make([
            'reason' => trim($reason),
            'paystack_refund_reference' => self::blankToNull($paystackRefundReference),
        ], [
            'reason' => ['required', 'string', 'max:500'],
            'paystack_refund_reference' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'],
        ])->validate();

        if (!$passwordConfirmed) {
            throw ValidationException::withMessages(['confirmation' => 'Confirm your password to record a refund.']);
        }

        if ($transaction->status !== TransactionStatus::PaidUncredited) {
            throw ValidationException::withMessages([
                'refund' => 'Only payments that were received but not counted can be refunded.',
            ]);
        }

        try {
            // Its own transaction: when a caller's transaction is open this is a savepoint, so a duplicate rolls
            // back only this insert (PostgreSQL otherwise aborts the caller's whole transaction).
            $record = DB::transaction(static function () use ($transaction, $actor, $data): RefundRecord {
                $record = new RefundRecord();
                $record->forceFill([
                    'vote_transaction_id' => $transaction->id,
                    'organization_id' => $transaction->organization_id,
                    'recorded_by_user_id' => $actor->id,
                    'reason' => $data['reason'],
                    'paystack_refund_reference' => $data['paystack_refund_reference'],
                ])->save();

                return $record;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['refund' => 'This refund has already been recorded.']);
        }

        Audit::record(
            'refund.recorded',
            $actor,
            $transaction,
            ['after' => ['refund_record_id' => $record->id]],
            [
                'gross_amount' => $transaction->gross_amount,
                'has_paystack_reference' => $record->paystack_refund_reference !== null,
            ],
        );

        return $record;
    }

    private static function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
