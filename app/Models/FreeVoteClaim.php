<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['award_category_id', 'vote_record_id', 'voter_key', 'period', 'slot'])]
/**
 * period is kept as a plain 'Y-m-d' string so the unique key compares identically on every database.
 */
final class FreeVoteClaim extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<VoteRecord, $this>
     */
    public function voteRecord(): BelongsTo
    {
        return $this->belongsTo(VoteRecord::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
