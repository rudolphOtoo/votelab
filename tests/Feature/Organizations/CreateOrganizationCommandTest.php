<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
});

it('creates the organization and emails its first owner an invitation', function (): void {
    $audit = captureAuditLog();

    $this
        ->artisan('organizations:create', [
            'name' => 'Accra Creatives',
            'slug' => 'accra-creatives',
            '--owner' => 'Owner@Example.com',
        ])
        ->expectsOutputToContain('Created organization')
        ->expectsOutputToContain('Owner invitation emailed')
        ->assertSuccessful();

    $organization = Organization::query()->where('slug', 'accra-creatives')->sole();
    $invitation = OrganizationInvitation::query()->sole();
    expect($invitation->organization_id)
        ->toBe($organization->id)
        ->and($invitation->role)
        ->toBe(OrganizationRole::Owner)
        ->and($invitation->email)
        ->toBe('owner@example.com')
        ->and($invitation->invited_by_user_id)
        ->toBeNull();
    Mail::assertQueued(OrganizationInvitationMail::class, 1);
    expect($audit->hasInfoThatContains('organization.created'))
        ->toBeTrue()
        ->and($audit->hasInfoThatContains('organizations.create_command'))
        ->toBeTrue();
});

it('is idempotent: no second organization, no rename, no second invitation', function (): void {
    $args = ['name' => 'Accra Creatives', 'slug' => 'accra-creatives', '--owner' => 'owner@example.com'];
    $this->artisan('organizations:create', $args)->assertSuccessful();

    $this
        ->artisan('organizations:create', [...$args, 'name' => 'Renamed'])
        ->expectsOutputToContain('already exists; left unchanged')
        ->expectsOutputToContain('still pending; not sent again')
        ->assertSuccessful();

    expect(Organization::query()->count())
        ->toBe(1)
        ->and(Organization::query()->sole()->name)
        ->toBe('Accra Creatives')
        ->and(OrganizationInvitation::query()->count())
        ->toBe(1);
    Mail::assertQueued(OrganizationInvitationMail::class, 1);
});

it('reports an existing owner without inviting again', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();

    $this
        ->artisan('organizations:create', ['name' => 'x', 'slug' => $organization->slug, '--owner' => $owner->email])
        ->expectsOutputToContain('is already an owner')
        ->assertSuccessful();

    Mail::assertNothingQueued();
});

it('fails when the owner email is already a member with another role', function (): void {
    $organization = organizer()->organizations()->firstOrFail();
    $staff = organizer(OrganizationRole::Staff, $organization);

    $this->artisan('organizations:create', [
        'name' => 'x',
        'slug' => $organization->slug,
        '--owner' => $staff->email,
    ])->assertFailed();
});

it('rejects reserved and malformed slugs', function (string $slug): void {
    $this->artisan('organizations:create', [
        'name' => 'X',
        'slug' => $slug,
        '--owner' => 'o@example.com',
    ])->assertFailed();

    expect(Organization::query()->count())->toBe(0);
})->with(['dashboard', 'stage', 'login', 'api', 'livewire-abc', 'Has Spaces', 'UPPER', '-leading', 'a']);

it('requires --owner', function (): void {
    $this->artisan('organizations:create', ['name' => 'X', 'slug' => 'fine-slug'])->assertFailed();
});

it('refuses to run in production without --force', function (): void {
    app()->detectEnvironment(static fn(): string => 'production');

    $this
        ->artisan('organizations:create', ['name' => 'X', 'slug' => 'prod-org', '--owner' => 'o@example.com'])
        ->expectsOutputToContain('without --force')
        ->assertFailed();
    expect(Organization::query()->count())->toBe(0);

    $this->artisan('organizations:create', [
        'name' => 'X',
        'slug' => 'prod-org',
        '--owner' => 'o@example.com',
        '--force' => true,
    ])->assertSuccessful();
    expect(Organization::query()->count())->toBe(1);
});

it('makes the invited owner able to accept and sign in', function (): void {
    $this->artisan('organizations:create', [
        'name' => 'X',
        'slug' => 'x-org',
        '--owner' => 'first@example.com',
    ])->assertSuccessful();

    expect(User::query()->count())->toBe(0); // No account until the invitation is accepted.
});
