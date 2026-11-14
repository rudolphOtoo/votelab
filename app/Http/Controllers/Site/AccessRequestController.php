<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Mail\AccessRequestReceivedMail;
use App\Models\AccessRequest;
use App\Services\Security\VoteFingerprintService;
use App\Support\Site\SiteContext;
use App\Support\Throttle;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Organizer "Request access" (plan/SITE.md decision 2, 8): a plain form, stored and emailed (queued) to the support
 * address. Spam protection: a honeypot field, a minimum time to fill the form (a signed timestamp in the form), and
 * per-device and per-network limits ("access-request"). A bot that trips the honeypot or the timer sees the normal
 * confirmation, but nothing is stored or sent.
 */
final class AccessRequestController
{
    /** Faster than this, a form was filled in by a script, not a person. */
    public const int MINIMUM_SECONDS = 3;

    public function create(Request $request): View
    {
        return view('site.request-access', [
            ...SiteContext::shared(),
            'started' => Crypt::encryptString((string) now()->getTimestamp()),
            'eventTypes' => AccessRequest::EVENT_TYPES,
            'sent' => $request->session()->get('access_request_sent') === true,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, VoteFingerprintService $fingerprints): RedirectResponse
    {
        $retryAfter = Throttle::attempt('access-request', $fingerprints->identify($request));

        if ($retryAfter !== null) {
            throw ValidationException::withMessages([
                'form' =>
                    'Too many requests from this device or network. Please try again in '
                        . (int) ceil($retryAfter / 60)
                        . ' minutes, or contact us directly.',
            ]);
        }

        if (self::looksAutomated($request)) {
            return self::sent(); // the same confirmation a person sees; nothing stored or sent
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'organization' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9 ()-]{7,}$/'],
            'event_type' => ['required', Rule::in(array_keys(AccessRequest::EVENT_TYPES))],
            'expected_date' => ['nullable', 'date', 'after_or_equal:today'],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [
            'phone.regex' => 'Enter a phone number with digits only, e.g. +233 24 123 4567.',
            'expected_date.after_or_equal' => 'Choose today or a later date.',
        ]);

        $accessRequest = AccessRequest::query()->create([...$data, 'email' => mb_strtolower(trim($data['email']))]);
        Mail::to((string) config('site.support.email'))->queue(new AccessRequestReceivedMail($accessRequest));

        return self::sent();
    }

    private static function looksAutomated(Request $request): bool
    {
        if (trim((string) $request->input('website')) !== '') {
            return true; // the honeypot: hidden from people, filled in by bots
        }

        try {
            $started = (int) Crypt::decryptString((string) $request->input('started'));
        } catch (DecryptException) {
            return true;
        }

        return (now()->getTimestamp() - $started) < self::MINIMUM_SECONDS;
    }

    private static function sent(): RedirectResponse
    {
        return redirect()->route('site.request-access')->with('access_request_sent', true);
    }
}
