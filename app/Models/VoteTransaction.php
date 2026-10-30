<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionStatus;
use Database\Factories\VoteTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'organization_id',
    'award_program_id',
    'award_category_id',
    'nominee_id',
    'quantity',
    'unit_price',
    'gross_amount',
    'fee_amount',
    'net_amount',
    'currency',
    'payment_provider',
    'payment_reference',
    'status',
    'status_reason',
    'voter_email',
    'metadata',
])]
final class VoteTransaction extends Model
{
    /** @use HasFactory<VoteTransactionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<AwardProgram, $this>
     */
    public function awardProgram(): BelongsTo
    {
        return $this->belongsTo(AwardProgram::class);
    }

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
     * @return HasOne<VoteRecord, $this>
     */
    public function voteRecord(): HasOne
    {
        return $this->hasOne(VoteRecord::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'gross_amount' => 'integer',
            'fee_amount' => 'integer',
            'net_amount' => 'integer',
            'metadata' => 'array',
            'verified_at' => 'immutable_datetime',
            'credited_at' => 'immutable_datetime',
        ];
    }
}
