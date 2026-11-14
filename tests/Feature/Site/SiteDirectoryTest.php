<?php

declare(strict_types=1);

use App\Actions\Admin\SaveProgramAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProgramStatus;
use App\Livewire\Admin\ProgramManager;
use App\Models\AuditEvent;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Support\DedicatedSite;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/*
 * The public site's lists (SiteDirectory): the homepage, /results and sitemap.xml. Only published programs not hidden
 * from the homepage, and only revealed categories' winners. Distinctive counts and names make any leak detectable.
 */

/** Nominee name => [category status, votes]. Only the revealed category's top nominee may ever appear. */
const SITE_NOMINEES = [
    'Wendy Winner' => [CategoryStatus::Revealed, 444_444],
    'Rex Runner' => [CategoryStatus::Revealed, 222_222],
    'Hannah Hidden' => [CategoryStatus::Embargoed, 987_654],
    'Cora Closed' => [CategoryStatus::Closed, 123_457],
    'Olu Open' => [CategoryStatus::Open, 555_321],
    'Dora Draft' => [CategoryStatus::Draft, 765_432],
];

function siteProgram(
    string $title = 'Campus Awards',
    ?Organization $organization = null,
    ProgramStatus $status = ProgramStatus::Published,
): AwardProgram {
    $program = AwardProgram::factory()->for($organization ?? Organization::factory()->create())->create([
        'title' => $title,
        'status' => $status,
        'ends_at' => now()->addDays(3),
    ]);
    $categories = [];

    foreach (SITE_NOMINEES as $name => [$categoryStatus, $votes]) {
        $categories[$categoryStatus->value] ??= AwardCategory::factory()
            ->for($program)
            ->status($categoryStatus)
            ->create(['name' => ucfirst($categoryStatus->value) . ' Category of ' . $title]);
        withLedgerVotes(Nominee::factory()->for($categories[$categoryStatus->value])->create([
            'name' => $name,
            'stage_name' => null,
        ]), $votes);
    }

    return $program;
}

function assertNothingHiddenIn(Illuminate\Testing\TestResponse $response): void
{
    foreach (SITE_NOMINEES as $name => [, $votes]) {
        $response->assertDontSee(number_format($votes))->assertDontSee((string) $votes);
        if ($name !== 'Wendy Winner') {
            $response->assertDontSee($name);
        }
    }
}

it('lists live programs and revealed winners on the homepage, and nothing hidden', function (): void {
    siteProgram('Campus Awards');

    $response = $this
        ->get('/')
        ->assertOk()
        ->assertSee('Campus Awards')
        ->assertSee('Wendy Winner')
        ->assertSee('Revealed Category of Campus Awards');

    assertNothingHiddenIn($response);
});

it('shows revealed winners on /results, never a hidden, closed or open category', function (): void {
    siteProgram('Campus Awards');

    $response = $this
        ->get('/results')
        ->assertOk()
        ->assertSee('Wendy Winner')
        ->assertDontSee('Embargoed Category of')
        ->assertDontSee('Closed Category of');

    assertNothingHiddenIn($response);
});

it('lists every winner of a tie', function (): void {
    $program = siteProgram('Tie Awards');
    Nominee::query()->where('name', 'Rex Runner')->update(['votes_count' => 444_444]);
    AwardCategory::query()->where('award_program_id', $program->id)->first()?->touch(); // clears the site cache

    $this
        ->get('/results')
        ->assertOk()
        ->assertSee('Wendy Winner')
        ->assertSee('Rex Runner')
        ->assertSee('A tie for first place.');
});

it('lists URLs and dates only in the sitemap: listed programs and their public categories', function (): void {
    $program = siteProgram('Campus Awards');
    $hidden = siteProgram('Hidden Awards');
    app(SaveProgramAction::class)->setHiddenFromHomepage(
        $hidden,
        organizer(OrganizationRole::Owner, $hidden->organization),
        true,
    );
    siteProgram('Draft Awards', status: ProgramStatus::Draft);

    $xml = $this
        ->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();
    $draftCategory = AwardCategory::query()
        ->where('award_program_id', $program->id)
        ->where('status', CategoryStatus::Draft)
        ->sole();

    expect($xml)
        ->toContain(route('public.program', [$program->organization, $program]))
        ->toContain(route('site.results'))
        ->toContain(route('site.privacy'))
        ->not->toContain('/' . $draftCategory->slug . '<')
        ->not->toContain($hidden->slug)
        ->not->toContain('draft-awards')
        ->not->toContain('987654')
        ->not->toContain('Wendy Winner');
});

it('leaves hidden and unpublished programs off the homepage and /results, but keeps their own page', function (): void {
    $hidden = siteProgram('Hidden Awards');
    siteProgram('Draft Awards', status: ProgramStatus::Draft);
    $owner = organizer(OrganizationRole::Owner, $hidden->organization);

    app(SaveProgramAction::class)->setHiddenFromHomepage($hidden, $owner, true);

    $this
        ->get('/')
        ->assertOk()
        ->assertDontSee('Hidden Awards')
        ->assertDontSee('Draft Awards')
        ->assertSee('No awards are open for voting right now.');
    $this->get('/results')->assertOk()->assertDontSee('Wendy Winner')->assertSee('No results have been announced yet.');
    $this
        ->get(route('public.program', [$hidden->organization, $hidden]))
        ->assertOk()
        ->assertSee('Hidden Awards');
});

it('lets owners and admins hide a program from the homepage, audited, and refuses staff', function (): void {
    $program = siteProgram('Campus Awards');
    $admin = organizer(OrganizationRole::Admin, $program->organization);

    expect(app(SaveProgramAction::class)->setHiddenFromHomepage($program, $admin, true)->isListed())->toBeFalse();
    $audit = AuditEvent::query()->where('action', 'program.listing_changed')->sole();
    expect($audit->before)
        ->toEqual([AwardProgram::HIDDEN_FROM_HOMEPAGE => false])
        ->and($audit->after)
        ->toEqual([AwardProgram::HIDDEN_FROM_HOMEPAGE => true]);

    expect(fn() => app(SaveProgramAction::class)->setHiddenFromHomepage(
        $program,
        organizer(OrganizationRole::Staff, $program->organization),
        false,
    ))
        ->toThrow(AuthorizationException::class);
    expect($program->fresh()?->isListed())->toBeFalse();
});

it('offers the switch on the program screen and shows the program again when it is turned off', function (): void {
    $program = siteProgram('Campus Awards');
    $this->actingAs(organizer(OrganizationRole::Owner, $program->organization));

    Livewire::test(ProgramManager::class, ['program' => $program->fresh()])
        ->assertSee('Hide from the VoteLab homepage')
        ->assertSeeHtml('role="switch" aria-checked="false"')
        ->call('toggleHomepageListing')
        ->assertSee('Hidden from the VoteLab homepage.')
        ->assertSeeHtml('role="switch" aria-checked="true"')
        ->call('toggleHomepageListing')
        ->assertSee('Shown on the VoteLab homepage again.');

    expect($program->fresh()?->isListed())->toBeTrue();
    $this->get('/')->assertSee('Campus Awards');
});

it('updates the cached lists as soon as a category changes status', function (): void {
    $program = siteProgram('Campus Awards');
    $this->get('/')->assertSee('Wendy Winner'); // cached now

    // A new reveal appears at once, without waiting for the cache to expire.
    $category = AwardCategory::query()
        ->where('award_program_id', $program->id)
        ->where('status', CategoryStatus::Closed)
        ->sole();
    $category->forceFill(['status' => CategoryStatus::Revealed])->save();

    $this->get('/')->assertSee('Cora Closed');
});

it('scopes every list to the organization in dedicated mode', function (): void {
    $ours = siteProgram('Our Awards', Organization::factory()->create(['slug' => 'ours']));
    siteProgram('Their Awards');
    AwardCategory::query()->where('award_program_id', $ours->id)->update(['status' => CategoryStatus::Draft]); // no live program, no winners
    AwardProgram::query()->whereKey($ours->id)->update(['status' => ProgramStatus::Draft]);
    PlatformSetting::set(DedicatedSite::MODE_SETTING, DedicatedSite::MODE_DEDICATED);
    PlatformSetting::set(DedicatedSite::ORG_SETTING, 'ours');

    $this
        ->get('/')
        ->assertOk()
        ->assertSee('No live program right now')
        ->assertSee($ours->organization->name)
        ->assertDontSee('Their Awards');
    $this->get('/results')->assertOk()->assertDontSee('Wendy Winner')->assertDontSee('Their Awards');
    expect($this->get('/sitemap.xml')->getContent())->not->toContain('their-awards');
});
