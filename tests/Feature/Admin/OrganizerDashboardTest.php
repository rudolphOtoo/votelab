<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\TransactionStatus;
use App\Livewire\Admin\OrganizationSettings;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use App\Support\ProgramOverview;
use Livewire\Livewire;

// UI Step 3: the organizer shell, program overview, onboarding and organization settings.

/**
 * A program with one category, 7 votes yesterday and 5 today (Accra), one pending payment and one refund owed.
 *
 * @return array{0: AwardProgram, 1: AwardCategory}
 */
function overviewProgram(CategoryStatus $status = CategoryStatus::Open): array
{
    $program = AwardProgram::factory()->create(['title' => 'Campus Awards 2026']);
    $category = AwardCategory::factory()->for($program)->status($status)->create();
    $nominee = Nominee::factory()->for($category)->create();

    foreach ([[7, now()->subDay()], [5, now()]] as [$quantity, $at]) {
        VoteRecord::query()->create([
            'organization_id' => $program->organization_id,
            'award_program_id' => $program->id,
            'award_category_id' => $category->id,
            'nominee_id' => $nominee->id,
            'is_paid' => false,
            'quantity' => $quantity,
            'voter_fingerprint' => str_repeat('a', 64),
            'ip_hash' => str_repeat('b', 64),
            'cast_at' => $at,
        ]);
    }

    VoteTransaction::factory()->for($nominee)->create(['status' => TransactionStatus::Pending]);
    VoteTransaction::factory()->for($nominee)->create(['status' => TransactionStatus::PaidUncredited]);

    return [$program, $category];
}

/**
 * @return array<string, string>
 */
function tilesFor(AwardProgram $program, OrganizationRole $role): array
{
    $user = organizer($role, $program->organization);

    return collect(ProgramOverview::tiles($program, $user))
        ->mapWithKeys(fn(array $t) => [$t['key'] => $t['value']])->all();
}

it('shows owners every overview tile, with votes from the ledger and today in Accra time', function (): void {
    [$program] = overviewProgram();

    expect(tilesFor($program, OrganizationRole::Owner))
        ->toMatchArray([
            'votes_today' => '5',
            'votes_total' => '12',
            'pending' => '1',
            'refunds' => '1',
        ])
        ->toHaveKey('revenue');
});

it('shows each tile only to someone allowed to see it', function (): void {
    [$program] = overviewProgram();
    $staff = tilesFor($program, OrganizationRole::Staff);
    $user = organizer(OrganizationRole::Staff, $program->organization);

    expect(array_key_exists('revenue', $staff))
        ->toBe($user->can('viewRevenue', $program))
        ->and(array_key_exists('pending', $staff))
        ->toBe($user->can('viewTransactions', $program))
        ->and(array_key_exists('refunds', $staff))
        ->toBe($user->can('viewRefunds', $program));
});

it('never reveals hidden results through program totals', function (): void {
    [$program, $category] = overviewProgram(CategoryStatus::Embargoed);
    $staff = organizer(OrganizationRole::Staff, $program->organization);

    expect($staff->can('viewHiddenResults', $category))
        ->toBeFalse()
        ->and(tilesFor($program, OrganizationRole::Staff))
        ->not->toHaveKey('votes_total')
        ->not->toHaveKey('votes_today')->and(tilesFor($program, OrganizationRole::Owner))->toHaveKey('votes_total');

    $this->actingAs($staff)->get("/dashboard/programs/{$program->id}")->assertOk()->assertDontSee('Total votes');
});

it('turns the launch checklist into a progress card with a fix link per open item', function (): void {
    $program = AwardProgram::factory()->create(['status' => App\Enums\ProgramStatus::Draft]);
    $owner = organizer(OrganizationRole::Owner, $program->organization);

    $this
        ->actingAs($owner)
        ->get("/dashboard/programs/{$program->id}")
        ->assertOk()
        ->assertSee('role="progressbar"', escape: false)
        ->assertSeeInOrder(['Launch checklist', 'of 9 done'])
        ->assertSee('Fix<span class="sr-only">: At least one category</span>', escape: false)
        ->assertSee(route('dashboard.programs.categories', $program), escape: false)
        ->assertSee('Publishing unlocks when every item is done.');
});

it('walks a new organization through its first program', function (): void {
    $organization = Organization::factory()->create();
    $owner = organizer(OrganizationRole::Owner, $organization);
    $staff = organizer(OrganizationRole::Staff, $organization);

    $this
        ->actingAs($owner)
        ->get('/dashboard')
        ->assertSeeInOrder(['Create the program', 'Add categories', 'Add nominees', 'Get the share kit', 'Publish'])
        ->assertSee('Create your first program');
    $this
        ->actingAs($staff)
        ->get('/dashboard')
        ->assertSee('An owner or admin')
        ->assertDontSee('Create your first program');
});

it('switches organization from the shell link, only to one the user belongs to', function (): void {
    $user = organizer(OrganizationRole::Owner, Organization::factory()->create(['name' => 'Alpha Org']));
    $second = Organization::factory()->create(['name' => 'Zulu Org']);
    $second->addMember($user, OrganizationRole::Staff);
    AwardProgram::factory()->for($second)->create(['title' => 'Zulu Awards']);

    $this
        ->actingAs($user)
        ->get('/dashboard?organization=' . $second->id)
        ->assertOk()
        ->assertSee('Zulu Awards');
    $this->actingAs($user)->get('/dashboard')->assertSee('Zulu Awards'); // kept in the session

    $stranger = Organization::factory()->create();
    $this
        ->actingAs($user)
        ->get('/dashboard?organization=' . $stranger->id)
        ->assertForbidden();
});

it('wraps organizer pages in the shell: skip link, section navigation, never indexed', function (): void {
    $owner = organizer();

    $this
        ->actingAs($owner)
        ->get('/dashboard')
        ->assertSee('Skip to content')
        ->assertSee('<meta name="robots" content="noindex">', escape: false)
        ->assertSee('aria-label="Sections"', escape: false)
        ->assertSee('Sign out');
});

it('shows the settings tab only to someone who can change a setting', function (): void {
    $organization = Organization::factory()->create();
    $owner = organizer(OrganizationRole::Owner, $organization);
    $staff = organizer(OrganizationRole::Staff, $organization);
    $url = route('dashboard.members', [$organization, 'tab' => 'settings']);

    $this->actingAs($owner)->get($url)->assertSee('Payout account')->assertSee('Change link');
    $this->actingAs($staff)->get($url)->assertDontSee('Payout account')->assertSee('Members');
});

it('saves name and logo directly, and asks for the password before link or payout changes', function (): void {
    $organization = Organization::factory()->create(['slug' => 'old-slug', 'name' => 'Old Name']);
    $owner = organizer(OrganizationRole::Owner, $organization);

    Livewire::actingAs($owner)
        ->test(OrganizationSettings::class, ['organization' => $organization])
        ->set('name', 'New Name')
        ->call('saveProfile')
        ->assertSee('Name, logo and colour saved.')
        ->set('slug', 'new-slug')
        ->call('saveLink')
        ->assertRedirect(route('password.confirm'));

    expect($organization->refresh()->name)->toBe('New Name')->and($organization->slug)->toBe('old-slug');

    confirmPassword();
    Livewire::actingAs($owner)
        ->test(OrganizationSettings::class, ['organization' => $organization])
        ->set('slug', 'new-slug')
        ->call('saveLink')
        ->assertRedirect(route('dashboard.members', ['organization' => 'new-slug', 'tab' => 'settings']));

    expect($organization->refresh()->slug)->toBe('new-slug');
});

it('confirms removing, leaving and revoking in a sheet, not a browser dialog', function (): void {
    $organization = Organization::factory()->create();
    $owner = organizer(OrganizationRole::Owner, $organization);
    organizer(OrganizationRole::Staff, $organization);

    $this
        ->actingAs($owner)
        ->get(route('dashboard.members', $organization))
        ->assertDontSee('wire:confirm', escape: false)
        ->assertSee('role="dialog"', escape: false)
        ->assertSee('Leave this organization');
});
