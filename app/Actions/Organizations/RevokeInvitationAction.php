<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Withdraws a pending invitation; its link stops working at once. Revoking a used, expired or already
 * revoked invitation changes nothing.
 */
final readonly class RevokeInvitationAction
{
    public function execute(OrganizationInvitation $invitation, User $actor): bool
    {
        $revoked = OrganizationInvitation::query()
            ->whereKey($invitation->id)
            ->pending()
            ->update([
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->id,
                'revoked_reason' => OrganizationInvitation::REASON_REVOKED,
            ]) === 1;

        if ($revoked) {
            Log::channel('audit')->info('invitation.revoked', [
                'organization_id' => $invitation->organization_id,
                'invitation_id' => $invitation->id,
                'revoked_by_user_id' => $actor->id,
            ]);
        }

        return $revoked;
    }
}
