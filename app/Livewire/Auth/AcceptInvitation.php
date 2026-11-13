<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Organizations\AcceptInvitationAction;
use App\Exceptions\Organizations\InvitationUnavailableException;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The page behind an emailed invitation link (route middleware `signed` checks the URL first). Shows why a
 * link can't be used, asks a new person for a name and password, asks an existing account to sign in, and
 * tells a signed-in user with a different email what to do.
 */
#[Layout('layouts::app', ['bundle' => 'auth'])]
final class AcceptInvitation extends Component
{
    #[Locked]
    public int $invitationId;

    #[Locked]
    public string $token = '';

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(OrganizationInvitation $invitation, Request $request): void
    {
        $this->invitationId = $invitation->id;
        $this->token = (string) $request->query('token', '');

        // An existing account signs in first and comes straight back here.
        if (Auth::guest()) {
            session()->put('url.intended', $request->fullUrl());
        }
    }

    public function accept(AcceptInvitationAction $accept): void
    {
        $invitation = $this->invitation();
        $signedIn = Auth::user();

        try {
            $user = $accept->execute($invitation, $this->token, $signedIn instanceof User ? $signedIn : null, [
                'name' => $this->name,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ]);
        } catch (InvitationUnavailableException) {
            $this->reset('password', 'password_confirmation');

            return; // render() shows the reason.
        }

        if (!$signedIn instanceof User) {
            Auth::login($user);
            session()->regenerate();
        }

        session()->forget('url.intended');
        CurrentOrganization::switchTo($user, $invitation->organization_id);
        session()->flash('status', "Welcome to {$invitation->organization->name}.");

        $this->redirectRoute('dashboard');
    }

    public function render(): View
    {
        $invitation = $this->invitation();
        $signedIn = Auth::user();
        $problem = null;

        try {
            AcceptInvitationAction::assertUsable($invitation, $this->token);

            if ($signedIn instanceof User && strtolower($signedIn->email) !== $invitation->email) {
                throw InvitationUnavailableException::wrongAccount($invitation->email, $signedIn->email);
            }

            if (!$signedIn instanceof User && User::query()->where('email', $invitation->email)->exists()) {
                throw InvitationUnavailableException::signInRequired($invitation->email);
            }
        } catch (InvitationUnavailableException $e) {
            $problem = $e;
        }

        return view('livewire.auth.accept-invitation', [
            'invitation' => $invitation,
            'problem' => $problem,
            'signedIn' => $signedIn instanceof User ? $signedIn : null,
        ])->title('Join ' . $invitation->organization->name);
    }

    private function invitation(): OrganizationInvitation
    {
        return OrganizationInvitation::query()->with('organization')->findOrFail($this->invitationId);
    }
}
