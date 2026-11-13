<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryStatus;
use App\Enums\VotingMode;
use App\Support\PermissionLookups;
use Carbon\CarbonImmutable;
use Database\Factories\AwardCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Required relations (NOT NULL foreign keys):
 *
 * @property-read Organization $organization
 * @property-read AwardProgram $awardProgram
 */
#[Fillable([
    'organization_id',
    'award_program_id',
    'name',
    'slug',
    'description',
    'voting_mode',
    'price_per_vote',
    'free_vote_limit',
    'status',
    'closed_at',
    'close_grace_minutes',
    'display_order',
])]
final class AwardCategory extends Model
{
    /** @use HasFactory<AwardCategoryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Any save may change the status: permission checks later in this request must read it again.
        static::saved(static fn(self $category) => PermissionLookups::forgetCategory($category->id));
        static::deleted(static fn(self $category) => PermissionLookups::forgetCategory($category->id));
    }

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
     * @return HasMany<Nominee, $this>
     */
    public function nominees(): HasMany
    {
        return $this->hasMany(Nominee::class)->orderBy('display_order')->orderBy('id');
    }

    /**
     * @return HasMany<VoteTransaction, $this>
     */
    public function voteTransactions(): HasMany
    {
        return $this->hasMany(VoteTransaction::class);
    }

    /**
     * @return HasMany<CategoryStatusTransition, $this>
     */
    public function statusTransitions(): HasMany
    {
        return $this->hasMany(CategoryStatusTransition::class);
    }

    /**
     * The moment late payments stop being credited, or null while the category is not closed.
     */
    public function graceEndsAt(): ?CarbonImmutable
    {
        if ($this->status !== CategoryStatus::Closed || $this->closed_at === null) {
            return null;
        }

        return $this->closed_at->addMinutes($this->graceMinutes());
    }

    /**
     * The grace window this category closed with (copied from its program at close, D-94), never more than
     * the platform maximum. Categories closed before D-94 have no copy and use the maximum.
     */
    public function graceMinutes(): int
    {
        $max = (int) config('voting.close_grace_minutes');

        return min($max, $this->close_grace_minutes ?? $max);
    }

    public function isWithinCloseGrace(?CarbonImmutable $at = null): bool
    {
        $endsAt = $this->graceEndsAt();

        return $endsAt !== null && ($at ?? CarbonImmutable::now())->lessThan($endsAt);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CategoryStatus::class,
            'close_grace_minutes' => 'integer',
            'voting_mode' => VotingMode::class,
            'price_per_vote' => 'integer',
            'free_vote_limit' => 'integer',
            'closed_at' => 'immutable_datetime',
        ];
    }
}
