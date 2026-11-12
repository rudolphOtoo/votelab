<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Fortify\PasswordValidationRules;
use App\Exceptions\Organizations\InvitationUnavailableException;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Accepts an invitation exactly once and adds the person to the organization with the invited role.
 *
 * - A signed-in user must be the invited email; a guest whose email already has an account must sign in.
 * - A guest without an account sets a name and password; the email counts as verified (they got the link).
 * - Single use: a conditional UPDATE (still pending, not expired) must affect exactly one row, inside the
 *   same transaction that creates the user and the membership, so a reused or raced link changes nothing.
 */
final readonly class AcceptInvitationAction
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $newAccount  name, password, password_confirmation (guests without an account)
     *
     * @throws InvitationUnavailableException
     * @throws ValidationException When the new account details are invalid.
     */
    public function execute(
        OrganizationInvitation $invitation,
        #[SensitiveParameter]
        string $token,
        ?User $signedIn,
        array $newAccount = [],
    ): User {
        self::assertUsable($invitation, $token);

        if ($signedIn !== null && Str::lower($signedIn->email) !== $invitation->email) {
            throw InvitationUnavailableException::wrongAccount($invitation->email, $signedIn->email);
        }

        $existing = $signedIn ?? User::query()->where('email', $invitation->email)->first();

        if ($signedIn === null && $existing !== null) {
            throw InvitationUnavailableException::signInRequired($invitation->email);
        }

        if ($existing === null) {
            Validator::make($newAccount, [
                'name' => ['required', 'string', 'max:255'],
                'password' => $this->passwordRules(),
            ])->validate();
        }

        $user = DB::transaction(static function () use ($invitation, $existing, $newAccount): User {
            Organization::query()->whereKey($invitation->organization_id)->lockForUpdate()->first();

            $user = $existing ?? self::createUser($invitation->email, $newAccount);

            $claimed = OrganizationInvitation::query()
                ->whereKey($invitation->id)
                ->pending()
                ->update(['accepted_at' => now(), 'accepted_by_user_id' => $user->id]);

            if ($claimed !== 1) {
                throw InvitationUnavailableException::used();
            }

            $organization = $invitation->organization;

            if ($organization->users()->whereKey($user->id)->exists()) {
                throw InvitationUnavailableException::alreadyMember();
            }

            $organization->addMember($user, $invitation->role);

            return $user;
        });

        Log::channel('audit')->info('invitation.accepted', [
            'organization_id' => $invitation->organization_id,
            'invitation_id' => $invitation->id,
            'user_id' => $user->id,
            'role' => $invitation->role->value,
            'new_account' => $existing === null,
        ]);

        return $user;
    }

    /**
     * @throws InvitationUnavailableException
     */
    public static function assertUsable(OrganizationInvitation $invitation, #[SensitiveParameter] string $token): void
    {
        if (!$invitation->matchesToken($token)) {
            throw InvitationUnavailableException::invalid();
        }

        if ($invitation->revoked_at !== null) {
            throw InvitationUnavailableException::revoked();
        }

        if ($invitation->accepted_at !== null) {
            throw InvitationUnavailableException::used();
        }

        if (!$invitation->expires_at->isFuture()) {
            throw InvitationUnavailableException::expired();
        }
    }

    /**
     * @param  array<string, mixed>  $newAccount
     */
    private static function createUser(string $email, array $newAccount): User
    {
        $user = new User();
        $user->forceFill([
            'name' => trim((string) $newAccount['name']),
            'email' => $email,
            'password' => Hash::make((string) $newAccount['password']),
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
