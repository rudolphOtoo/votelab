<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AccessRequest;
use App\Support\Queues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A new organizer "Request access" submission, to the support email (plan/SITE.md decision 8). Queued; reply-to is
 * the person who asked.
 */
final class AccessRequestReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly AccessRequest $accessRequest,
    ) {
        $this->onQueue(Queues::MAIL);
    }

    public function envelope(): Envelope
    {
        return new Envelope(replyTo: [new Address(
            $this->accessRequest->email,
            $this->accessRequest->name,
        )], subject: "Request for access: {$this->accessRequest->organization}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.access-request-received', with: ['request' => $this->accessRequest]);
    }
}
