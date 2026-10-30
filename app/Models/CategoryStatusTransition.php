<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['award_category_id', 'user_id', 'from_status', 'to_status'])]
final class CategoryStatusTransition extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<AwardCategory, $this>
     */
    public function awardCategory(): BelongsTo
    {
        return $this->belongsTo(AwardCategory::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => CategoryStatus::class,
            'to_status' => CategoryStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
