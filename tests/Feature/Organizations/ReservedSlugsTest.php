<?php

declare(strict_types=1);

use App\Rules\OrganizationSlug;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

it('reserves the first path segment of every fixed route, so no organization can shadow one', function (): void {
    $segments = collect(Route::getRoutes()->getRoutes())
        ->map(static fn(Illuminate\Routing\Route $route): string => explode('/', trim($route->uri(), '/'))[0])
        ->filter(static fn(string $segment): bool => $segment !== '' && !str_starts_with($segment, '{'))
        ->unique()
        ->values();

    $unreserved = $segments->reject(OrganizationSlug::isReserved(...));

    expect($unreserved->all())->toBeEmpty();
});

it('validates organization slugs', function (string $slug, bool $valid): void {
    $passes = Validator::make(['slug' => $slug], ['slug' => [new OrganizationSlug()]])->passes();

    expect($passes)->toBe($valid);
})->with([
    ['accra-campus-creatives', true],
    ['ab',                     true],
    ['dashboard',              false],
    ['livewire-1a2b',          false],
    ['two--hyphens',           false],
    ['trailing-',              false],
    ['Capital',                false],
    [str_repeat('a', 65),   false],
]);
