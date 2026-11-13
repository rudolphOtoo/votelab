<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Livewire\Admin\Dashboard;
use App\Models\AwardProgram;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Livewire\Livewire;

it('shows only the organizations and programs the user belongs to', function (): void {
    $user = organizer(OrganizationRole::Staff);
    $mine = $user->organizations()->firstOrFail();
    AwardProgram::factory()->for($mine)->create(['title' => 'Our Awards 2026']);
    $theirs = Organization::factory()->create(['name' => 'Someone Else Ltd']);
    AwardProgram::factory()->for($theirs)->create(['title' => 'Their Secret Awards']);

    $this
        ->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee($mine->name)
        ->assertSee('Our Awards 2026')
        ->assertDontSee('Someone Else Ltd')
        ->assertDontSee('Their Secret Awards');
});

it('offers a switcher to members of several organizations and switches between them', function (): void {
    $user = organizer(OrganizationRole::Staff);
    $second = Organization::factory()->create(['name' => 'Zebra Events']);
    $second->addMember($user, OrganizationRole::Staff);
    AwardProgram::factory()->for($second)->create(['title' => 'Zebra Awards']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('id="organization-switcher"', escape: false)
        ->call('switchOrganization', $second->id)
        ->assertSee('Zebra Awards');

    expect(session(CurrentOrganization::SESSION_KEY))->toBe($second->id);
});

it('refuses to switch to an organization the user is not in', function (): void {
    $user = organizer(OrganizationRole::Staff);
    $other = Organization::factory()->create();

    Livewire::actingAs($user)->test(Dashboard::class)->call('switchOrganization', $other->id)->assertForbidden();

    expect(session(CurrentOrganization::SESSION_KEY))->toBeNull();
});

it('ignores a forged session value pointing at another organization', function (): void {
    $user = organizer(OrganizationRole::Staff);
    $other = Organization::factory()->create(['name' => 'Not Yours Inc']);

    $this
        ->actingAs($user)
        ->withSession([CurrentOrganization::SESSION_KEY => $other->id])
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee('Not Yours Inc');
});

it('explains what to do when the user has no organization yet', function (): void {
    $this
        ->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('not part of an organization yet');
});
