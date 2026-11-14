<?php

declare(strict_types=1);

/*
 * Lists the `run:` steps of one job in .github/workflows/ci.yml, in order, for scripts/ci-local.sh:
 *
 *   php scripts/ci-local-steps.php <workflow.yml> <job>    → one line per step: "<name>\t<base64 of the command>"
 *
 * A reader for the shape VoteLab's workflow uses, not a general YAML parser (the project has none, and adding one
 * would be a new dependency): `jobs:` → `<job>:` → `steps:` → list items whose `name:` and `run:` are plain
 * scalars or `|` / `>` / `>-` blocks. tests/Unit/CiLocalStepsTest.php reads the real workflow with it, so a change
 * to ci.yml this reader can't follow fails CI rather than running the wrong commands locally.
 */

/**
 * @return list<array{name: string, run: string}>
 */
function ci_local_steps(string $workflow, string $job): array
{
    $lines = preg_split('/\R/', $workflow) ?: [];
    $count = count($lines);
    $indent = static fn(string $line): int => strlen($line) - strlen(ltrim($line, ' '));
    $i = 0;

    // Find "  <job>:" under "jobs:", then its "steps:".
    while ($i < $count && rtrim($lines[$i]) !== 'jobs:') {
        $i++;
    }
    while ($i < $count && rtrim($lines[$i]) !== "  {$job}:") {
        $i++;
    }
    if ($i >= $count) {
        throw new RuntimeException("No job \"{$job}\" in the workflow.");
    }
    $i++;
    while ($i < $count && trim($lines[$i]) !== 'steps:') {
        if (trim($lines[$i]) !== '' && $indent($lines[$i]) <= 2) {
            throw new RuntimeException("Job \"{$job}\" has no steps."); // reached the next job
        }
        $i++;
    }
    if ($i >= $count) {
        throw new RuntimeException("Job \"{$job}\" has no steps.");
    }
    $stepsIndent = $indent($lines[$i]);
    $i++;

    $steps = [];
    $current = null;

    while ($i < $count) {
        $line = $lines[$i];
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            $i++;
            continue;
        }

        $at = $indent($line);
        if ($at <= $stepsIndent) {
            break; // the job's next key, or the next job
        }

        if (str_starts_with($trimmed, '- ')) {
            if ($current !== null) {
                $steps[] = $current;
            }
            $current = ['name' => null, 'run' => null];
            $keyIndent = $at + 2;
            $line = str_repeat(' ', $keyIndent) . substr($trimmed, 2);
            $lines[$i] = $line;
            $trimmed = trim($line);
            $at = $keyIndent;
        }

        if ($current !== null && preg_match('/^([A-Za-z-]+):\s*(.*)$/', $trimmed, $m) === 1) {
            [$key, $value] = [$m[1], $m[2]];
            $i++;

            if (in_array($value, ['|', '|-', '>', '>-'], true)) {
                $block = [];
                while ($i < $count && (trim($lines[$i]) === '' || $indent($lines[$i]) > $at)) {
                    $block[] = $lines[$i];
                    $i++;
                }
                while ($block !== [] && trim(end($block)) === '') {
                    array_pop($block);
                }
                $blockIndent = min(array_map($indent, array_filter($block, static fn(string $l): bool => trim($l) !== '')));
                $block = array_map(static fn(string $l): string => substr($l, $blockIndent), $block);
                $value = str_starts_with($value, '|') ? implode("\n", $block) : implode(' ', array_map('trim', $block));
            } else {
                $value = trim($value, " '\"");
            }

            if ($key === 'name' || $key === 'run') {
                $current[$key] = $value;
            }

            continue;
        }

        $i++;
    }

    if ($current !== null) {
        $steps[] = $current;
    }

    $runs = [];
    foreach ($steps as $step) {
        if ($step['run'] === null) {
            continue; // a `uses:` step
        }
        $runs[] = ['name' => $step['name'] ?? strtok($step['run'], "\n"), 'run' => $step['run']];
    }

    return $runs;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    if ($argc !== 3) {
        fwrite(STDERR, "usage: php scripts/ci-local-steps.php <workflow.yml> <job>\n");
        exit(2);
    }

    try {
        foreach (ci_local_steps((string) file_get_contents($argv[1]), $argv[2]) as $step) {
            echo $step['name'], "\t", base64_encode($step['run']), "\n";
        }
    } catch (RuntimeException $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}
