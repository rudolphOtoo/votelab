<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued: sent by the queue worker. The signed accept URL carries the one-time token, which is not
 * stored anywhere else in plain form.
 */
final class OrganizationInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly OrganizationInvitation $invitation,
        public readonly string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You're invited to {$this->invitation->organization->name} on VoteLab");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.organization-invitation', with: [
            'organizationName' => $this->invitation->organization->name,
            'role' => $this->invitation->role->label(),
            'expiresAt' => $this->invitation->expires_at,
            'acceptUrl' => $this->acceptUrl,
        ]);
    }
}
