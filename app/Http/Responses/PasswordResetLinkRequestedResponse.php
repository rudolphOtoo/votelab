<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * The same answer whether or not the email has an account (or was throttled), so the forgot-password
 * form can't be used to discover who is an organizer. Bound to both Fortify contracts.
 */
final class PasswordResetLinkRequestedResponse implements
    SuccessfulPasswordResetLinkRequestResponse,
    FailedPasswordResetLinkRequestResponse
{
    public const string MESSAGE = 'If an account exists for that email, we have sent a password reset link.';

    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        return (
            $request->wantsJson()
                ? new JsonResponse(['message' => self::MESSAGE], 200)
                : back()->with('status', self::MESSAGE)
        );
    }
}
