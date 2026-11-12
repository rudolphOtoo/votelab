<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\Throttle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Invites an email address to an organization with a role and queues the email.
 *
 * - The email is trimmed and lower-cased; an existing member is refused.
 * - A pending invitation to the same email is revoked as "superseded", so only the newest link works.
 * - The token is random, emailed inside a signed URL that expires with the invitation, and stored only
 *   as a SHA-256 hash.
 * - Invitations from a person are rate limited per inviter and per organization. The CLI (no inviter)
 *   is not rate limited.
 *
 * Who may invite, and the password confirmation for owner invitations, are the caller's checks
 * (OrganizationPolicy::manageMembers, OrganizationMembers).
 */
final readonly class InviteMemberAction
{
    /**
     * @throws ValidationException When the email is invalid, already a member, or the inviter is rate limited.
     */
    public function execute(
        Organization $organization,
        string $email,
        OrganizationRole $role,
        ?User $inviter,
    ): OrganizationInvitation {
        $email = Str::lower(trim($email));

        Validator::make(['email' => $email], ['email' => ['required', 'string', 'email', 'max:255']])->validate();

        if ($organization->users()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This person is already a member of the organization.',
            ]);
        }

        if ($inviter !== null) {
            $retryAfter = Throttle::attempt('organization-invitations', $inviter, $organization);

            if ($retryAfter !== null) {
                $minutes = (int) ceil($retryAfter / 60);

                throw ValidationException::withMessages([
                    'email' => "You've sent a lot of invitations. Try again in {$minutes} minute(s).",
                ]);
            }
        }

        $token = Str::random(48);

        $invitation = DB::transaction(static function () use (
            $organization,
            $email,
            $role,
            $inviter,
            $token,
        ): OrganizationInvitation {
            // Serialize invitations to one organization, so two concurrent re-invites can't both stay pending.
            Organization::query()->whereKey($organization->id)->lockForUpdate()->first();

            $superseded = OrganizationInvitation::query()
                ->where('organization_id', $organization->id)
                ->where('email', $email)
                ->pending()
                ->update([
                    'revoked_at' => now(),
                    'revoked_by_user_id' => $inviter?->id,
                    'revoked_reason' => OrganizationInvitation::REASON_SUPERSEDED,
                ]);

            $invitation = new OrganizationInvitation();
            $invitation->forceFill([
                'organization_id' => $organization->id,
                'email' => $email,
                'role' => $role,
                'token_hash' => OrganizationInvitation::hashToken($token),
                'invited_by_user_id' => $inviter?->id,
                'expires_at' => now()->addHours((int) config('organizations.invitation_ttl_hours')),
            ])->save();

            Log::channel('audit')->info('invitation.created', [
                'organization_id' => $organization->id,
                'invitation_id' => $invitation->id,
                'role' => $role->value,
                'invited_by_user_id' => $inviter?->id,
                'superseded' => $superseded,
            ]);

            return $invitation;
        });

        $invitation->setRelation('organization', $organization);

        Mail::to($email)->queue(new OrganizationInvitationMail($invitation, self::acceptUrl($invitation, $token)));

        return $invitation;
    }

    public static function acceptUrl(OrganizationInvitation $invitation, #[SensitiveParameter] string $token): string
    {
        return URL::temporarySignedRoute('invitations.accept', $invitation->expires_at, [
            'invitation' => $invitation->id,
            'token' => $token,
        ]);
    }
}
