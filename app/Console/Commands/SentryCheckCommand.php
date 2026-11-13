<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\Severity;
use Sentry\State\HubInterface;

/**
 * Sends one test event to the configured Sentry project so an operator can confirm, in Sentry itself, that
 * phone numbers and email addresses are scrubbed (D-112). The event carries fake Ghanaian numbers and a fake
 * email in its message, extra data, tags and a breadcrumb; in Sentry they must all read [phone] / [email].
 * Refuses to run in production.
 */
final class SentryCheckCommand extends Command
{
    public const string FAKE_PHONE_LOCAL = '024 555 0199';

    public const string FAKE_PHONE_INTERNATIONAL = '+233 24 555 0199';

    public const string FAKE_EMAIL = 'test.voter@example.com';

    protected $signature = 'ops:sentry-check';

    protected $description = 'Send a test event with fake phone numbers and an email to Sentry to confirm scrubbing (not in production)';

    public function handle(HubInterface $hub): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('ops:sentry-check does not run in production. Run it from staging with the same DSN.');

            return self::FAILURE;
        }

        if ((string) config('sentry.dsn') === '' || $hub->getClient() === null) {
            $this->error('SENTRY_LARAVEL_DSN is not set; nothing to check.');

            return self::FAILURE;
        }

        $hub->addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_DEFAULT,
            'ops',
            'Test breadcrumb for ' . self::FAKE_PHONE_INTERNATIONAL,
            [
                'email' => self::FAKE_EMAIL,
            ],
        ));
        $eventId = $hub->captureEvent(self::testEvent());
        $hub->getClient()->flush();

        $this->info('Sent test event ' . ($eventId ?? '(no id: the event was dropped)') . '.');
        $this->line(
            'In Sentry, open it and confirm the message, extra data, tags and breadcrumb show [phone] and [email],',
        );
        $this->line(
            'and that "'
            . self::FAKE_PHONE_LOCAL
            . '", "'
            . self::FAKE_PHONE_INTERNATIONAL
            . '" and "'
            . self::FAKE_EMAIL
            . '" appear nowhere.',
        );

        return $eventId === null ? self::FAILURE : self::SUCCESS;
    }

    public static function testEvent(): Event
    {
        $event = Event::createEvent();
        $event->setLevel(Severity::warning());
        $event->setMessage(
            'VoteLab scrubbing check: voter ' . self::FAKE_EMAIL . ' paid from ' . self::FAKE_PHONE_LOCAL,
        );
        $event->setExtra([
            'payer_phone' => self::FAKE_PHONE_INTERNATIONAL,
            'payer_email' => self::FAKE_EMAIL,
            'note' => 'Every value above must read [phone] or [email] in Sentry.',
        ]);
        $event->setTags(['check' => 'pii-scrubbing', 'contact' => self::FAKE_EMAIL]);

        return $event;
    }
}
