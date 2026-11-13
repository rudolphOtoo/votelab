<?php

declare(strict_types=1);

namespace App\Support\Observability;

use Illuminate\Support\Facades\Log;
use Sentry\State\Scope;

use function Sentry\captureMessage;
use function Sentry\withScope;

/**
 * Something an operator must look at now (D-108): failed jobs, fulfilment.gave_up, tally drift, a production
 * guard failure. Logged at error level on the `alerts` channel and sent to Sentry (when SENTRY_LARAVEL_DSN is
 * set) as an error with its context, scrubbed of phone numbers and emails. Context must be ids and codes only.
 */
final class Alert
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    public static function raise(string $event, array $context = []): void
    {
        Log::channel('alerts')->error($event, $context);

        withScope(static function (Scope $scope) use ($event, $context): void {
            $scope->setTag('alert', $event);
            $scope->setContext('alert', PiiScrubber::array($context));
            captureMessage($event, \Sentry\Severity::error());
        });
    }
}
