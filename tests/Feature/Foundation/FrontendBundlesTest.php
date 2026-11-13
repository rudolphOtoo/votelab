<?php

declare(strict_types=1);

/*
 * What each page must download before it works, read from the production build's manifest (cleanup batch 6).
 * CI builds the assets before running the tests.
 */

/**
 * @return array<string, array{file: string, imports?: list<string>, dynamicImports?: list<string>}>
 */
function viteManifest(): array
{
    return json_decode(
        (string) file_get_contents(public_path('build/manifest.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

/**
 * Every manifest key an entry imports statically, itself included.
 *
 * @return list<string>
 */
function staticImports(string $entry): array
{
    $manifest = viteManifest();
    $seen = [];
    $queue = [$entry];

    while ($queue !== []) {
        $key = array_shift($queue);

        if (array_key_exists($key, $seen)) {
            continue;
        }

        $seen[$key] = true;
        array_push($queue, ...$manifest[$key]['imports'] ?? []);
    }

    return array_keys($seen);
}

it('serves the payment result page without Livewire or Alpine', function (): void {
    $files = array_map(
        static fn(string $key): string => viteManifest()[$key]['file'],
        staticImports('resources/js/entries/result.js'),
    );

    expect($files)->toHaveCount(1)->and(implode(' ', $files))->not->toContain('livewire');
});

it('loads the passkeys client on the dashboard only when a passkey is used', function (): void {
    $client = 'node_modules/@laravel/passkeys/dist/index.js';
    $dynamic = collect(viteManifest())->flatMap(static fn(array $chunk): array => $chunk['dynamicImports'] ?? []);

    expect(staticImports('resources/js/entries/admin.js'))
        ->not
        ->toContain($client)
        ->and($dynamic->all())
        ->toContain($client);
});
