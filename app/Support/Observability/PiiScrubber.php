<?php

declare(strict_types=1);

namespace App\Support\Observability;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;

/**
 * Removes phone numbers and email addresses from everything sent to Sentry (D-107): messages, exception
 * values, request data, extra data, contexts, tags and breadcrumbs; and drops the user's IP and email.
 * Registered as Sentry's before_send and before_breadcrumb. send_default_pii stays off as well.
 */
final class PiiScrubber
{
    // Ghana numbers in local (0XXXXXXXXX) or international (+233 / 233, spaces or dashes) form, and any other
    // run of 9–15 digits that looks like a phone number.
    private const string PHONE = '/(?<![\w.])(?:\+?233[\s-]?|0)\d{2}[\s-]?\d{3}[\s-]?\d{4}(?![\w.])|(?<![\w.])\+\d[\d\s-]{8,16}\d(?![\w.])/';

    private const string EMAIL = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/';

    public static function text(string $value): string
    {
        return (string) preg_replace([self::EMAIL, self::PHONE], ['[email]', '[phone]'], $value);
    }

    public static function value(mixed $value): mixed
    {
        return match (true) {
            is_string($value) => self::text($value),
            is_array($value) => self::array($value),
            default => $value,
        };
    }

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, mixed>  $values
     * @return array<TKey, mixed>
     */
    public static function array(array $values): array
    {
        return array_map(self::value(...), $values);
    }

    public static function beforeSend(Event $event, ?EventHint $hint = null): Event
    {
        self::scrubMessage($event);

        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(self::text($exception->getValue()));
        }

        $event->setRequest(self::array($event->getRequest()));
        $event->setExtra(self::array($event->getExtra()));
        $event->setTags(array_map(self::text(...), $event->getTags()));

        foreach ($event->getContexts() as $name => $data) {
            $event->setContext((string) $name, self::array($data));
        }

        $event->setBreadcrumb(array_map(self::breadcrumb(...), $event->getBreadcrumbs()));
        $event->getUser()?->setIpAddress(null)->setEmail(null);

        return $event;
    }

    private static function scrubMessage(Event $event): void
    {
        $message = $event->getMessage();

        if ($message === null) {
            return;
        }

        $formatted = $event->getMessageFormatted();
        $event->setMessage(
            self::text($message),
            array_map(self::text(...), $event->getMessageParams()),
            $formatted === null ? null : self::text($formatted),
        );
    }

    public static function beforeBreadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        return self::breadcrumb($breadcrumb);
    }

    private static function breadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        $scrubbed = $breadcrumb->getMessage() === null
            ? $breadcrumb
            : $breadcrumb->withMessage(self::text($breadcrumb->getMessage()));

        foreach ($scrubbed->getMetadata() as $name => $value) {
            $scrubbed = $scrubbed->withMetadata((string) $name, self::value($value));
        }

        return $scrubbed;
    }
}
