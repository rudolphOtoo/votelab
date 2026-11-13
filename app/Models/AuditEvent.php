<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One entry of the append-only audit trail (D-90). Written only by App\Support\Audit; the model refuses
 * updates and deletes, and a test checks no app code issues them through the query builder.
 *
 * @property int|null $organization_id
 * @property int|null $actor_user_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property array<string, mixed>|null $context
 * @property string|null $ip_address
 * @property Carbon $created_at
 */
final class AuditEvent extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(static fn(): never => throw new LogicException('Audit events are append-only.'));
        static::deleting(static fn(): never => throw new LogicException('Audit events are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
