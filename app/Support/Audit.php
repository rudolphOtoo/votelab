<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The organizer audit trail (D-90): who did what, to which record, with the values before and after.
 * Each entry is a row in the append-only audit_events table (shown in ResultsAuditor) and a copy in
 * storage/logs/audit-*.log. Callers pass ids and settings, never voter contact details, tokens or secrets.
 */
final class Audit
{
    /**
     * @param  Model|null  $subject  The record acted on; its organization is taken from it when it has one.
     * @param  array{before?: array<string, mixed>, after?: array<string, mixed>}  $change
     * @param  array<string, mixed>  $context  Extra facts (reason, counts); no personal data. An
     *                                         'organization_id' entry names the organization when the
     *                                         subject has none (e.g. a member, who is a User).
     */
    public static function record(
        string $action,
        ?User $actor,
        ?Model $subject = null,
        array $change = [],
        array $context = [],
    ): AuditEvent {
        $before = $change['before'] ?? [];
        $after = $change['after'] ?? [];
        $given = $context['organization_id'] ?? null;
        unset($context['organization_id']);
        $organizationId = is_int($given) ? $given : self::organizationOf($subject);
        $subjectType = $subject === null ? null : Str::snake(class_basename($subject));
        $subjectId = $subject?->getKey();
        $ip = app()->runningInConsole() && !app()->runningUnitTests() ? null : request()->ip();

        $event = new AuditEvent();
        $event->forceFill([
            'organization_id' => $organizationId,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => is_int($subjectId) ? $subjectId : null,
            'before' => $before === [] ? null : $before,
            'after' => $after === [] ? null : $after,
            'context' => $context === [] ? null : $context,
            'ip_address' => $ip,
        ])->save();

        Log::channel('audit')->info($action, [
            'audit_event_id' => $event->id,
            'actor_user_id' => $actor?->id,
            'organization_id' => $organizationId,
            'subject_type' => $subjectType,
            'subject_id' => $event->subject_id,
            ...$context,
            'before' => $before,
            'after' => $after,
        ]);

        return $event;
    }

    private static function organizationOf(?Model $subject): ?int
    {
        if ($subject instanceof Organization) {
            return $subject->id;
        }

        $organizationId = $subject?->getAttribute('organization_id');

        return is_int($organizationId) ? $organizationId : null;
    }
}
