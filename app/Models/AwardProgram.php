<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProgramStatus;
use Database\Factories\AwardProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    protected function casts(): array
    {
        return [
            'status' => ProgramStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'settings' => 'array',
        ];
    }
}
