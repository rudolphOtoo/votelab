<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NomineeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * votes_count is a derived counter. It is not mass assignable: it only changes
 * through atomic increments when votes are credited, or a rebuild from vote_records.
 */
#[Fillable([
    'organization_id',
    'award_program_id',
    'award_category_id',
    'name',
    'stage_name',
    'nominee_code',
    'avatar_url',
    'bio',
    'display_order',
])]
final class Nominee extends Model
{
    /** @use HasFactory<NomineeFactory> */
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
     * @return HasMany<VoteTransaction, $this>
     */
    public function voteTransactions(): HasMany
    {
        return $this->hasMany(VoteTransaction::class);
    }

    /**
     * @return HasMany<VoteRecord, $this>
     */
    public function voteRecords(): HasMany
    {
        return $this->hasMany(VoteRecord::class);
    }

    public function displayName(): string
    {
        return $this->stage_name ?? $this->name;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'votes_count' => 'integer',
        ];
    }
}
