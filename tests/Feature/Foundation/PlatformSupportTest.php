<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProgramStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\ActiveProgram;
use App\Support\DedicatedSite;
use Illuminate\Support\Carbon;

it('stores platform settings and invalidates the cached value on write', function (): void {
    expect(PlatformSetting::get('missing', 'fallback'))->toBe('fallback');

    PlatformSetting::set('deployment_mode', 'dedicated');
    expect(PlatformSetting::get('deployment_mode'))->toBe('dedicated');

    PlatformSetting::set('deployment_mode', 'multi_tenant');
    expect(PlatformSetting::get('deployment_mode'))->toBe('multi_tenant');
});

it('does not resolve a dedicated site in multi-tenant mode', function (): void {
    $program = AwardProgram::factory()->create();
    PlatformSetting::set(DedicatedSite::MODE_SETTING, DedicatedSite::MODE_MULTI_TENANT);
    PlatformSetting::set(DedicatedSite::ORG_SETTING, $program->organization->slug);

    expect(DedicatedSite::resolve())->toBeNull();
});

it('resolves the latest published program of the home organization in dedicated mode', function (): void {
    $organization = Organization::factory()->create();
    AwardProgram::factory()->for($organization)->create(['starts_at' => now()->subYear()]);
    $current = AwardProgram::factory()->for($organization)->create(['starts_at' => now()]);
    AwardProgram::factory()->for($organization)->draft()->create(['starts_at' => now()->addMonth()]);

    PlatformSetting::set(DedicatedSite::MODE_SETTING, DedicatedSite::MODE_DEDICATED);
    PlatformSetting::set(DedicatedSite::ORG_SETTING, $organization->slug);

    $site = DedicatedSite::resolve();

    expect($site?->organization->id)->toBe($organization->id)->and($site?->program?->id)->toBe($current->id);
});

it('honours a pinned home program slug in dedicated mode', function (): void {
    $organization = Organization::factory()->create();
    $pinned = AwardProgram::factory()->for($organization)->create(['starts_at' => now()->subYear()]);
    AwardProgram::factory()->for($organization)->create(['starts_at' => now()]);

    PlatformSetting::set(DedicatedSite::MODE_SETTING, DedicatedSite::MODE_DEDICATED);
    PlatformSetting::set(DedicatedSite::ORG_SETTING, $organization->slug);
    PlatformSetting::set(DedicatedSite::PROGRAM_SETTING, $pinned->slug);

    expect(DedicatedSite::resolve()?->program?->id)->toBe($pinned->id);
});

it('returns no dedicated site when the home organization does not exist', function (): void {
    PlatformSetting::set(DedicatedSite::MODE_SETTING, DedicatedSite::MODE_DEDICATED);
    PlatformSetting::set(DedicatedSite::ORG_SETTING, 'nobody');

    expect(DedicatedSite::resolve())->toBeNull();
});

it('reports a user\'s role per organization', function (): void {
    $user = User::factory()->create();
    $owned = Organization::factory()->create();
    $staffed = Organization::factory()->create();
    $foreign = Organization::factory()->create();
    $owned->addMember($user, OrganizationRole::Owner);
    $staffed->addMember($user, OrganizationRole::Staff);

    expect($user->roleIn($owned))
        ->toBe(OrganizationRole::Owner)
        ->and($user->roleIn($staffed))
        ->toBe(OrganizationRole::Staff)
        ->and($user->roleIn($foreign))
        ->toBeNull();
});

it('only lets an organizer select programs from their own organizations', function (): void {
    $user = User::factory()->create();
    $mine = AwardProgram::factory()->create();
    $mine->organization->addMember($user, OrganizationRole::Admin);
    $other = AwardProgram::factory()->create();

    $active = app(ActiveProgram::class);
    $active->set($user, $mine);

    expect($active->for($user)?->id)->toBe($mine->id);

    $active->set($user, $other);
})->throws(InvalidArgumentException::class);

it('ignores a stale session selection for a program the user cannot access', function (): void {
    $user = User::factory()->create();
    $mine = AwardProgram::factory()->create();
    $mine->organization->addMember($user, OrganizationRole::Admin);
    $other = AwardProgram::factory()->create(['status' => ProgramStatus::Published]);

    session()->put(ActiveProgram::SESSION_KEY, $other->id);

    expect(app(ActiveProgram::class)->for($user)?->id)->toBe($mine->id);
});

it('tracks the close grace window of a closed category', function (): void {
    config(['voting.close_grace_minutes' => 5]);
    Carbon::setTestNow('2026-10-30 20:00:00');

    $category = AwardCategory::factory()->status(CategoryStatus::Closed)->create();

    expect($category->isWithinCloseGrace())
        ->toBeTrue()
        ->and($category->isWithinCloseGrace(now()->addMinutes(4)->toImmutable()))
        ->toBeTrue()
        ->and($category->isWithinCloseGrace(now()->addMinutes(5)->toImmutable()))
        ->toBeFalse();

    $open = AwardCategory::factory()->status(CategoryStatus::Open)->create();
    expect($open->graceEndsAt())->toBeNull()->and($open->isWithinCloseGrace())->toBeFalse();
});
