<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\Queues;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Sent by the queue worker, so a forgot-password request takes the same time whether or not the email
 * has an account (no timing hint, alongside PasswordResetLinkRequestedResponse's identical message).
 */
final class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $token
     */
    public function __construct(#[\SensitiveParameter] $token)
    {
        parent::__construct($token);
        $this->onQueue(Queues::MAIL);
    }
}
