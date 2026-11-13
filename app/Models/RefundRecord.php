<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Record that a paid_uncredited payment was refunded outside VoteLab (no money moves here). Append-only:
 * written by RecordRefundAction, never changed or deleted. One per transaction.
 *
 * @property int $vote_transaction_id
 * @property int $organization_id
 * @property int|null $recorded_by_user_id
 * @property string $reason
 * @property string|null $paystack_refund_reference
 * @property Carbon $created_at
 * @property-read VoteTransaction $voteTransaction
 */
final class RefundRecord extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return BelongsTo<VoteTransaction, $this>
     */
    public function voteTransaction(): BelongsTo
    {
        return $this->belongsTo(VoteTransaction::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    protected static function booted(): void
    {
        static::updating(static fn(): never => throw new LogicException('Refund records are append-only.'));
        static::deleting(static fn(): never => throw new LogicException('Refund records are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
