<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Real races on PostgreSQL (D-137): each side runs in its own process (tests/Concurrency/worker.php), with its own
 * database connection, against committed data. The test holds a row lock on a third connection, waits until
 * pg_stat_activity shows every side blocked on a lock, then lets them all go at once.
 */
final class Race
{
    private const string LOCK_CONNECTION = 'race_lock';

    public static function requirePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            test()->markTestSkipped('Needs PostgreSQL: runs in the PostgreSQL CI job (D-137).');
        }
    }

    /**
     * Start one side of the race in its own process.
     *
     * @param  array<string, scalar|null>  $args
     */
    public static function start(string $name, string $scenario, array $args = []): RaceProcess
    {
        $command = [
            PHP_BINARY,
            base_path('tests/Concurrency/worker.php'),
            $name,
            $scenario,
            json_encode($args, JSON_THROW_ON_ERROR),
        ];
        // The test's own environment (phpunit.xml's values included), pointed at this test process's database:
        // under --parallel each process has its own.
        $env = getenv() + ['APP_ENV' => 'testing'];
        $env['DB_CONNECTION'] = 'pgsql';
        $env['DB_DATABASE'] = (string) config('database.connections.pgsql.database');
        $env['DB_URL'] = '';

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);

        if (!is_resource($process)) {
            throw new RuntimeException("Could not start race worker [{$name}].");
        }

        return new RaceProcess($name, $process, $pipes);
    }

    /**
     * Run $while with a row locked FOR UPDATE on a separate connection; the lock is released afterwards, whatever
     * happens.
     *
     * @template T
     *
     * @param  Closure(): T  $while
     * @return T
     */
    public static function holdingRowLock(string $table, int $id, Closure $while): mixed
    {
        config(['database.connections.' . self::LOCK_CONNECTION => config('database.connections.pgsql')]);
        $lock = DB::connection(self::LOCK_CONNECTION);
        $lock->beginTransaction();

        try {
            $lock->table($table)->where('id', $id)->lockForUpdate()->first();

            return $while();
        } finally {
            $lock->rollBack();
            DB::purge(self::LOCK_CONNECTION);
        }
    }

    /**
     * Wait until every named side is blocked on a lock (pg_stat_activity), proving the database made it wait.
     *
     * @param  list<string>  $names
     */
    public static function waitUntilBlocked(array $names, float $seconds = 15.0): void
    {
        $deadline = microtime(true) + $seconds;
        $expected = array_map(static fn(string $name): string => "votelab-race-{$name}", $names);
        $blocked = [];

        while (microtime(true) < $deadline) {
            $blocked = DB::table('pg_stat_activity')
                ->whereIn('application_name', $expected)
                ->where('wait_event_type', 'Lock')
                ->pluck('application_name')
                ->all();

            if (count(array_unique($blocked)) === count($expected)) {
                return;
            }

            usleep(20_000);
        }

        throw new RuntimeException(
            'Not every side blocked on a lock in time. Blocked: ['
            . implode(', ', $blocked)
            . '], expected: ['
            . implode(', ', $expected)
            . '].',
        );
    }

    /**
     * Wait until a file appears (a worker paused at a known point inside its transaction).
     */
    public static function waitForFile(string $path, float $seconds = 15.0): void
    {
        $deadline = microtime(true) + $seconds;

        while (!file_exists($path)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException("Timed out waiting for {$path}.");
            }

            usleep(10_000);
        }
    }
}
