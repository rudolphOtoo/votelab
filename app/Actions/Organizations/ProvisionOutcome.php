<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

/**
 * What organizations:create did about the owner email.
 */
enum ProvisionOutcome: string
{
    case Invited = 'invited';
    case AlreadyOwner = 'already_owner';
    case InvitationPending = 'invitation_pending';
    case MemberWithOtherRole = 'member_with_other_role';
}
