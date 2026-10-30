<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VoteRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The vote ledger: one row per free vote or credited paid transaction.
 */
#[WithoutTimestamps]
#[Fillable([
    'organization_id',
    'award_program_id',
    'award_category_id',
    'nominee_id',
    'vote_transaction_id',
    'is_paid',
    'quantity',
    'voter_fingerprint',
    'ip_hash',
    'cast_at',
])]
final class VoteRecord extends Model
{
    /** @use HasFactory<VoteRecordFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AwardCategory, $this>
     */
    public function awardCategory(): BelongsTo
    {
        return $this->belongsTo(AwardCategory::class);
    }

    /**
     * @return BelongsTo<Nominee, $this>
     */
    public function nominee(): BelongsTo
    {
        return $this->belongsTo(Nominee::class);
    }

    /**
     * @return BelongsTo<VoteTransaction, $this>
     */
    public function voteTransaction(): BelongsTo
    {
        return $this->belongsTo(VoteTransaction::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'quantity' => 'integer',
            'cast_at' => 'immutable_datetime',
        ];
    }
}
