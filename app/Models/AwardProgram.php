<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProgramStatus;
use Database\Factories\AwardProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Required relations (NOT NULL foreign keys):
 *
 * @property-read Organization $organization
 */
#[Fillable([
    'organization_id',
    'title',
    'slug',
    'tagline',
    'description',
    'banner_url',
    'status',
    'starts_at',
    'ends_at',
    'settings',
])]
final class AwardProgram extends Model
{
    /** @use HasFactory<AwardProgramFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<AwardCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(AwardCategory::class)->orderBy('display_order')->orderBy('id');
    }

    /**
     * @return HasMany<Nominee, $this>
     */
    public function nominees(): HasMany
    {
        return $this->hasMany(Nominee::class);
    }

    /**
     * @return HasMany<VoteTransaction, $this>
     */
    public function voteTransactions(): HasMany
    {
        return $this->hasMany(VoteTransaction::class);
    }

    /**
     * @return array<string, string>
     */
    /** The settings key behind "Hide from the VoteLab homepage" (plan/SITE.md decision 3). */
    public const string HIDDEN_FROM_HOMEPAGE = 'hidden_from_homepage';

    /**
     * Whether the program may appear on the VoteLab homepage, /results and the sitemap. A hidden program stays
     * reachable by its own link.
     */
    public function isListed(): bool
    {
        return ($this->settings[self::HIDDEN_FROM_HOMEPAGE] ?? false) !== true;
    }

    /**
     * Programs not hidden from the VoteLab homepage (no setting, or the setting false).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeListed(Builder $query): Builder
    {
        return $query->where(
            static fn(Builder $q) => $q->whereNull('settings->' . self::HIDDEN_FROM_HOMEPAGE)->orWhere(
                'settings->' . self::HIDDEN_FROM_HOMEPAGE,
                false,
            ),
        );
    }

    /**
     * A program by id ("12") or by "organization-slug/program-slug", for commands.
     */
    public static function findByReference(string $reference): ?self
    {
        if (ctype_digit($reference)) {
            return self::query()->find((int) $reference);
        }

        [$organization, $program] = array_pad(explode('/', $reference, 2), 2, '');

        return self::query()
            ->whereHas('organization', static fn($query) => $query->where('slug', $organization))
            ->where('slug', $program)
            ->first();
    }

    protected function casts(): array
    {
        return [
            'status' => ProgramStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'settings' => 'array',
            'close_grace_minutes' => 'integer',
        ];
    }
}
