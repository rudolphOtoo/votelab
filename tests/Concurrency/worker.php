<?php

declare(strict_types=1);

/*
 * One side of a race, in its own process and so its own PostgreSQL connection (tests/Concurrency, D-137).
 * Started by Tests\Concurrency\Race with the test's environment; prints one JSON line: the outcome.
 *
 *   php tests/Concurrency/worker.php <name> <scenario> <json arguments>
 *
 * The cache is the array store, per process, so the fulfilment cache lock is NOT shared between the two sides:
 * these tests prove the database's own guards (row locks, conditional updates, unique keys) on their own.
 */

use App\Actions\Admin\RecountCategoryTallies;
use App\Actions\Organizations\ChangeMemberRoleAction;
use App\Actions\Organizations\RemoveMemberAction;
use App\Actions\Voting\CastFreeVoteAction;
use App\Actions\Voting\FulfillPaidVoteOrderAction;
use App\Contracts\PaymentGateway;
use App\Enums\OrganizationRole;
use App\Enums\PaymentTrigger;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\User;
use App\Models\VoteRecord;
use App\Services\Payment\Data\GatewayPaymentStatus;
use App\Services\Security\VoterIdentity;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakePaymentGateway;

require __DIR__ . '/../../vendor/autoload.php';

[, $name, $scenario, $json] = $argv;
/** @var array<string, mixed> $args */
$args = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Named, so the test can see this connection waiting on a lock in pg_stat_activity.
config(['database.connections.pgsql.application_name' => "votelab-race-{$name}"]);
DB::purge('pgsql');

/**
 * Optionally stop inside the action's transaction, right after its ledger row is written, until the test says go.
 */
$pauseAfterLedgerRow = static function (?string $dir): void {
    if ($dir === null) {
        return;
    }

    VoteRecord::created(static function () use ($dir): void {
        touch("{$dir}/paused");
        $deadline = microtime(true) + 20;

        while (!file_exists("{$dir}/go") && microtime(true) < $deadline) {
            usleep(10_000);
        }
    });
};

$run = match ($scenario) {
    'fulfil' => static function () use ($args, $pauseAfterLedgerRow): array {
        $gateway = new FakePaymentGateway()->reports(
            (string) $args['reference'],
            GatewayPaymentStatus::Success,
            (int) $args['amount'],
        );
        app()->instance(PaymentGateway::class, $gateway);
        $pauseAfterLedgerRow($args['pause_dir'] ?? null);

        $result = app(FulfillPaidVoteOrderAction::class)->execute(
            (string) $args['reference'],
            PaymentTrigger::from((string) $args['trigger']),
        );

        return ['newly_credited' => $result->newlyCredited];
    },
    'free-vote' => static function () use ($args): array {
        $record = app(CastFreeVoteAction::class)->execute(
            Nominee::query()->findOrFail((int) $args['nominee_id']),
            new VoterIdentity(voterKey: (string) $args['voter_key'], ipHash: (string) $args['voter_key']),
        );

        return ['vote_record_id' => $record->id];
    },
    'change-role' => static function () use ($args): array {
        app(ChangeMemberRoleAction::class)->execute(
            Organization::query()->findOrFail((int) $args['organization_id']),
            User::query()->findOrFail((int) $args['member_id']),
            OrganizationRole::from((string) $args['role']),
            User::query()->findOrFail((int) $args['actor_id']),
        );

        return [];
    },
    'remove-member' => static function () use ($args): array {
        app(RemoveMemberAction::class)->execute(
            Organization::query()->findOrFail((int) $args['organization_id']),
            User::query()->findOrFail((int) $args['member_id']),
            User::query()->findOrFail((int) $args['actor_id']),
        );

        return [];
    },
    'recount' => static fn(): array => [
        'drift' => RecountCategoryTallies::apply((int) $args['award_category_id']),
    ],
    default => throw new InvalidArgumentException("Unknown race scenario [{$scenario}]."),
};

try {
    $outcome = ['ok' => true] + $run();
} catch (Throwable $e) {
    $outcome = ['ok' => false, 'exception' => $e::class, 'message' => $e->getMessage()];
}

fwrite(STDOUT, json_encode($outcome, JSON_THROW_ON_ERROR) . "\n");
