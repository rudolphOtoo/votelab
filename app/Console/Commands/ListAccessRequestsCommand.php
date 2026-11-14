<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AccessRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The platform owner's view of organizer "Request access" submissions (plan/SITE.md decision 8). Personal data: shown
 * in this terminal only, never logged. Submissions are deleted 180 days after they arrive (model:prune, scheduled).
 */
#[Signature('access-requests:list {--days=30 : Show requests from the last this many days}')]
#[Description('List recent organizer "Request access" submissions')]
final class ListAccessRequestsCommand extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $requests = AccessRequest::query()->where('created_at', '>=', now()->subDays($days))->latest('id')->get();

        if ($requests->isEmpty()) {
            $this->info("No requests in the last {$days} days.");

            return self::SUCCESS;
        }

        $this->table([
            'Received',
            'Name',
            'Organization',
            'Email',
            'Phone',
            'Event',
            'Voting starts',
            'Message',
        ], $requests->map(static fn(AccessRequest $r): array => [
            $r->created_at?->format('Y-m-d H:i'),
            $r->name,
            $r->organization,
            $r->email,
            $r->phone,
            $r->eventTypeLabel(),
            $r->expected_date?->format('Y-m-d') ?? '',
            mb_strimwidth((string) $r->message, 0, 60, '…'),
        ])->all());

        return self::SUCCESS;
    }
}
