<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * The organizer audit trail (storage/logs/audit-*.log): who did what, to which record, with the values
 * before and after. Callers pass ids and settings, never voter contact details or secrets.
 */
final class Audit
{
    /**
     * @param  array<string, mixed>  $subject  e.g. ['award_category_id' => 7]
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function record(
        string $action,
        ?User $actor,
        array $subject,
        array $before = [],
        array $after = [],
    ): void {
        Log::channel('audit')->info($action, [
            'actor_user_id' => $actor?->id,
            ...$subject,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
