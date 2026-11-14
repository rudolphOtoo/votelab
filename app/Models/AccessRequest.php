<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * An organizer's "Request access" submission (plan/SITE.md decision 8). Personal data: never logged; listed for the
 * platform owner by `php artisan access-requests:list`; deleted site.access_request_retention_days (180) after it
 * arrived, by the scheduled `model:prune`.
 *
 * @property int $id
 * @property string $name
 * @property string $organization
 * @property string $email
 * @property string $phone
 * @property string $event_type
 * @property \Carbon\CarbonImmutable|null $expected_date
 * @property string|null $message
 * @property \Carbon\CarbonImmutable|null $created_at
 */
#[Fillable(['name', 'organization', 'email', 'phone', 'event_type', 'expected_date', 'message'])]
final class AccessRequest extends Model
{
    use Prunable;

    /** Event types offered on the form: key => label. */
    public const array EVENT_TYPES = [
        'campus' => 'Campus awards',
        'music' => 'Music or entertainment awards',
        'corporate' => 'Corporate or industry awards',
        'pageant' => 'Pageant',
        'community' => 'Community or church awards',
        'other' => 'Something else',
    ];

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()
            ->where('created_at', '<=', now()->subDays((int) config('site.access_request_retention_days')));
    }

    public function eventTypeLabel(): string
    {
        return self::EVENT_TYPES[$this->event_type] ?? $this->event_type;
    }

    protected function casts(): array
    {
        return [
            'expected_date' => 'immutable_date',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
