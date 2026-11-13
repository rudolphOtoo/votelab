<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use RuntimeException;

/**
 * One running side of a race (Race::start). wait() returns the worker's JSON outcome.
 */
final class RaceProcess
{
    /**
     * @param  resource  $process
     * @param  array<int, resource>  $pipes
     */
    public function __construct(
        public readonly string $name,
        private $process,
        private array $pipes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function wait(float $seconds = 30.0): array
    {
        $deadline = microtime(true) + $seconds;

        while (proc_get_status($this->process)['running']) {
            if (microtime(true) > $deadline) {
                proc_terminate($this->process);

                throw new RuntimeException("Race worker [{$this->name}] did not finish in {$seconds} s.");
            }

            usleep(10_000);
        }

        $out = (string) stream_get_contents($this->pipes[1]);
        $err = (string) stream_get_contents($this->pipes[2]);
        proc_close($this->process);
        $lines = array_values(array_filter(explode("\n", trim($out))));
        $outcome = json_decode((string) end($lines), true);

        if (!is_array($outcome)) {
            throw new RuntimeException("Race worker [{$this->name}] gave no outcome. stdout: {$out} stderr: {$err}");
        }

        return $outcome;
    }
}
