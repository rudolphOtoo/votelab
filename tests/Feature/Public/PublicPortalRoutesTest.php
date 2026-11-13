<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Support\DedicatedSite;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create(['slug' => 'campus-creatives']);
    $this->program = AwardProgram::factory()->for($this->organization)->create([
        'slug' => 'excellence-2026',
        'title' => 'Excellence Awards 2026',
    ]);
    $this->open = AwardCategory::factory()
        ->for($this->program)
        ->status(CategoryStatus::Open)
        ->create(['name' => 'Artiste of the Year', 'slug' => 'artiste']);
    $this->draft = AwardCategory::factory()
        ->for($this->program)
        ->status(CategoryStatus::Draft)
        ->create(['name' => 'Secret Draft Category', 'slug' => 'secret']);
    $this->nominee = Nominee::factory()->for($this->open)->create([
        'name' => 'Kwame Asante',
        'nominee_code' => 'C01-01',
    ]);
});

it('shows a published program with its non-draft categories only', function (): void {
    $this
        ->get('/campus-creatives/excellence-2026')
        ->assertOk()
        ->assertSee('Excellence Awards 2026')
        ->assertSee('Artiste of the Year')
        ->assertDontSee('Secret Draft Category');
});

it('hides unpublished programs', function (): void {
    $this->program->forceFill(['status' => App\Enums\ProgramStatus::Draft])->save();

    $this->get('/campus-creatives/excellence-2026')->assertNotFound();
    $this->get('/campus-creatives/excellence-2026/categories/artiste')->assertNotFound();
    $this->get('/campus-creatives/excellence-2026/C01-01')->assertNotFound();
});

it('scopes programs to their organization', function (): void {
    Organization::factory()->create(['slug' => 'someone-else']);

    $this->get('/someone-else/excellence-2026')->assertNotFound();
});

it('shows an open category and 404s a draft one', function (): void {
    $this->get('/campus-creatives/excellence-2026/categories/artiste')->assertOk()->assertSee('Kwame Asante');
    $this->get('/campus-creatives/excellence-2026/categories/secret')->assertNotFound();
});

it('scopes categories and nominee codes to their program', function (): void {
    $other = AwardProgram::factory()->for($this->organization)->create(['slug' => 'other-2026']);
    $otherCategory = AwardCategory::factory()->for($other)->create(['slug' => 'elsewhere']);
    Nominee::factory()->for($otherCategory)->create(['nominee_code' => 'X99-01']);

    $this->get('/campus-creatives/excellence-2026/categories/elsewhere')->assertNotFound();
    $this->get('/campus-creatives/excellence-2026/X99-01')->assertNotFound();
    $this->get('/campus-creatives/other-2026/X99-01')->assertOk();
});

it('opens the vote drawer at a nominee deep link', function (): void {
    $this
        ->get('/campus-creatives/excellence-2026/C01-01')
        ->assertOk()
        ->assertSee('role="dialog"', escape: false)
        ->assertSee('Vote for Kwame Asante');
});

it('404s a nominee deep link into a draft category', function (): void {
    Nominee::factory()->for($this->draft)->create(['nominee_code' => 'D01-01']);

    $this->get('/campus-creatives/excellence-2026/D01-01')->assertNotFound();
});

it('404s unknown organizations, programs, categories and codes', function (string $path): void {
    $this->get($path)->assertNotFound();
})->with([
    '/nobody/excellence-2026',
    '/campus-creatives/nothing',
    '/campus-creatives/excellence-2026/categories/nothing',
    '/campus-creatives/excellence-2026/ZZ-99',
]);

it('keeps fixed routes ahead of the public patterns', function (): void {
    $this->get('/payments/callback?reference=VL_UNKNOWN')->assertNotFound(); // handled by the callback controller
    $this->get(route('payments.callback'))->assertNotFound();
});

it('sets an anonymous device cookie for voters', function (): void {
    $this->get('/campus-creatives/excellence-2026')->assertCookie(config('voting.voter_cookie'));
});

it('serves the home program at / in dedicated mode', function (): void {
    PlatformSetting::set(DedicatedSite::MODE_SETTING, DedicatedSite::MODE_DEDICATED);
    PlatformSetting::set(DedicatedSite::ORG_SETTING, 'campus-creatives');

    $this->get('/')->assertOk()->assertSee('Excellence Awards 2026')->assertSee('Artiste of the Year');
});

it('serves the default page at / in multi-tenant mode', function (): void {
    $this->get('/')->assertOk()->assertDontSee('Excellence Awards 2026');
});
