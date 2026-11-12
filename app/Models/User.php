<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Notifications\QueuedResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use SensitiveParameter;

/**
 * @property Carbon|null $first_signed_in_at
 * @property int $session_epoch
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;
    use PasskeyAuthenticatable;
    use TwoFactorAuthenticatable;

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)->withPivot('role')->withTimestamps();
    }

    /**
     * The user's role in the given organization, or null when not a member.
     */
    public function roleIn(Organization|int $organization): ?OrganizationRole
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        /** @var string|null $role */
        $role = $this->organizations()->whereKey($organizationId)->first()?->pivot?->getAttribute('role');

        return $role === null ? null : OrganizationRole::from($role);
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }

    /**
     * Whether the user is an owner or admin of any organization, and so must use two-factor.
     */
    public function managesAnyOrganization(): bool
    {
        return $this
            ->organizations()
            ->wherePivotIn('role', [OrganizationRole::Owner->value, OrganizationRole::Admin->value])
            ->exists();
    }

    /**
     * Two-factor is on only once confirmed with a code (Fortify's 'confirm' option).
     */
    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'first_signed_in_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'session_epoch' => 'integer',
            // @mago-expect lint:no-literal-password
            'password' => 'hashed',
        ];
    }
}
