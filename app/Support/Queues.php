<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Named queues, so a slow job can never hold up a payment. Workers take them in priority order (README,
 * "Queue workers"): one Supervisor program runs payments, then mail, then default; another runs images alone.
 * Every job's timeout stays below the connection's retry_after (90 s), or a still-running job would be handed to a
 * second worker (QueueTimeoutsTest).
 */
final class Queues
{
    /** Webhook fulfilment (FulfillPaidVoteOrderJob): first, always. */
    public const string PAYMENTS = 'payments';

    /** Invitations, security notices, password resets. */
    public const string MAIL = 'mail';

    /** Portrait variants (GenerateNomineePhotoVariantsJob): CPU-heavy, on their own worker. */
    public const string IMAGES = 'images';
}
