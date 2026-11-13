<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;

// D-118: public pages can be restored from the back/forward cache; signed-in pages never are.

function publicPagesFixture(): void
{
    $organization = Organization::factory()->create(['slug' => 'campus']);
    $program = AwardProgram::factory()->for($organization)->create(['slug' => 'awards']);
    $category = AwardCategory::factory()->for($program)->status(CategoryStatus::Open)->create(['slug' => 'artiste']);
    Nominee::factory()->for($category)->create(['nominee_code' => 'AM27']);
}

it('lets the back/forward cache keep public pages', function (string $path): void {
    publicPagesFixture();

    $cacheControl = (string) $this->get($path)->assertOk()->headers->get('Cache-Control');

    expect($cacheControl)->not->toContain('no-store');
})->with([
    'program landing' => '/campus/awards',
    'category' => '/campus/awards/categories/artiste',
    'nominee deep link (drawer open)' => '/campus/awards/AM27',
]);

it('keeps signed-in pages out of the back/forward cache', function (): void {
    $this->actingAs(organizer());

    expect((string) $this->get('/dashboard')->assertOk()->headers->get('Cache-Control'))->toContain('no-store');
});
