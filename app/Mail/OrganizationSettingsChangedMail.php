<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Tells every owner that the organization's URL slug or payout account changed, who changed it and when,
 * so an unexpected change (e.g. a hijacked admin account redirecting payouts) is noticed at once.
 */
final class OrganizationSettingsChangedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<string>  $changes  Human-readable lines, e.g. "Payout account: ACCT_old → ACCT_new"
     */
    public function __construct(
        public readonly Organization $organization,
        public readonly string $changedBy,
        public readonly Carbon $changedAt,
        public readonly array $changes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Security notice: {$this->organization->name} settings changed");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.organization-settings-changed');
    }
}
