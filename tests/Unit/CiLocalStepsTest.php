<?php

declare(strict_types=1);

/*
 * scripts/ci-local.sh runs CI's own steps, read from .github/workflows/ci.yml by scripts/ci-local-steps.php (no YAML
 * library in the project). These tests read the real workflow with that reader, so a workflow change it can't follow
 * fails CI instead of making the local check run the wrong commands. They also hold D-147: every job creates .env
 * before Composer runs, because Composer's package discovery boots the app.
 */

require_once __DIR__ . '/../../scripts/ci-local-steps.php';

function workflow(): string
{
    return (string) file_get_contents(__DIR__ . '/../../.github/workflows/ci.yml');
}

/**
 * @return list<string>
 */
function workflowJobs(): array
{
    preg_match('/^jobs:\R(.*)\z/ms', workflow(), $match);
    preg_match_all('/^  ([a-z0-9-]+):\s*$/m', $match[1], $jobs);

    return $jobs[1];
}

it('reads every job of the workflow, each with at least one step to run', function (): void {
    expect(workflowJobs())->toContain('quality', 'postgres', 'mutation', 'browser-critical', 'browser-advisory');

    foreach (workflowJobs() as $job) {
        $steps = ci_local_steps(workflow(), $job);

        expect($steps)->not->toBeEmpty("job {$job}");
        foreach ($steps as $step) {
            expect(trim($step['run']))->not->toBeEmpty("a step of {$job}")->and($step['run'])->not->toContain('${{');
        }
    }
});

it('keeps multi-line and folded commands as CI runs them', function (): void {
    $quality = collect(ci_local_steps(workflow(), 'quality'))->pluck('run', 'name');
    $mutation = collect(ci_local_steps(workflow(), 'mutation'))->last();

    expect($quality['Prepare environment'])
        ->toBe("php artisan key:generate\ntouch database/database.sqlite")
        ->and($mutation['run'])
        ->toStartWith('./vendor/bin/pest --mutate --parallel --min=')
        ->toContain("--class='App\\Actions\\Voting\\DecidePaymentSettlement,")
        ->not->toContain("\n");
});

it('creates .env before composer install in every job that installs (D-147)', function (): void {
    foreach (workflowJobs() as $job) {
        $runs = array_column(ci_local_steps(workflow(), $job), 'run');
        $install = array_key_first(array_filter($runs, static fn(string $r): bool => str_starts_with(
            $r,
            'composer install',
        )));

        if ($install === null) {
            continue; // e.g. dependency-audit, which installs nothing
        }

        $env = array_key_first(array_filter($runs, static fn(string $r): bool => $r === 'cp .env.example .env'));
        expect($env)->not->toBeNull("job {$job} never creates .env")->and($env)->toBeLessThan($install, "job {$job}");
    }
});

it('refuses a job the workflow does not have', function (): void {
    ci_local_steps(workflow(), 'no-such-job');
})->throws(RuntimeException::class, 'No job "no-such-job"');
