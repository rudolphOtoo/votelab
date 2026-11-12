<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Http\Middleware\RequireTwoFactorForManagers;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Password, two-factor and passkeys. The forms post straight to Fortify's endpoints; this page stays open
 * when the two-factor requirement blocks the rest of the dashboard.
 */
#[Layout('layouts::dashboard')]
final class AccountSecurity extends Component
{
    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $twoFactorPending = $user->two_factor_secret !== null && !$user->hasConfirmedTwoFactor();

        return view('livewire.admin.account-security', [
            'user' => $user,
            'twoFactorOn' => $user->hasConfirmedTwoFactor(),
            'twoFactorPending' => $twoFactorPending,
            'qrCodeSvg' => $twoFactorPending ? $user->twoFactorQrCodeSvg() : null,
            'showRecoveryCodes' =>
                $user->hasConfirmedTwoFactor()
                    && in_array(
                        session('status'),
                        ['two-factor-authentication-confirmed', 'recovery-codes-generated'],
                        true,
                    ),
            'required' => $user->managesAnyOrganization(),
            'deadline' => $user->managesAnyOrganization() && !$user->hasConfirmedTwoFactor()
                ? RequireTwoFactorForManagers::deadline($user)
                : null,
            'passkeys' => $user->passkeys()->latest('id')->get(),
        ])->title('Security');
    }
}
