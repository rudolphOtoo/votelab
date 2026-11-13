<?php

declare(strict_types=1);

use App\Actions\Admin\RevealWinnerAction;
use App\Actions\Admin\TransitionCategoryStatusAction;
use App\Actions\Organizations\ChangeMemberRoleAction;
use App\Actions\Organizations\RemoveMemberAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Livewire\Admin\NomineeManager;
use App\Models\AwardCategory;
use App\Models\Nominee;
use App\Models\Organization;
use App\Support\PermissionLookups;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * @return list<string> SQL of every query run by $run
 */
function queriesDuring(Closure $run): array
{
    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    $run();

    return $queries;
}

function hiddenCategory(): AwardCategory
{
    return AwardCategory::factory()->status(CategoryStatus::Embargoed)->create();
}

it('asks the database for a role once per request, however many checks', function (): void {
    $category = hiddenCategory();
    $admin = organizer(OrganizationRole::Admin, $category->organization);

    $queries = queriesDuring(static function () use ($admin, $category): void {
        foreach (range(1, 10) as $ignored) {
            expect(Gate::forUser($admin)->allows('viewHiddenResults', $category))->toBeTrue();
        }
    });

    expect(array_filter($queries, static fn(string $sql): bool => str_contains($sql, 'organization_user')))
        ->toHaveCount(1);
});

it('sees a role change made earlier in the same request', function (): void {
    $category = hiddenCategory();
    $owner = organizer(OrganizationRole::Owner, $category->organization);
    $admin = organizer(OrganizationRole::Admin, $category->organization);
    expect(Gate::forUser($admin)->allows('viewHiddenResults', $category))->toBeTrue();

    app(ChangeMemberRoleAction::class)->execute($category->organization, $admin, OrganizationRole::Staff, $owner);

    expect(Gate::forUser($admin)->allows('viewHiddenResults', $category))->toBeFalse();
});

it('sees a removal made earlier in the same request', function (): void {
    $category = hiddenCategory();
    $owner = organizer(OrganizationRole::Owner, $category->organization);
    $admin = organizer(OrganizationRole::Admin, $category->organization);
    expect(Gate::forUser($admin)->allows('viewHiddenResults', $category))->toBeTrue();

    app(RemoveMemberAction::class)->execute($category->organization, $admin, $owner);

    expect(Gate::forUser($admin)->allows('viewHiddenResults', $category))->toBeFalse();
});

it('sees a membership added earlier in the same request', function (): void {
    $category = hiddenCategory();
    $newcomer = organizer(OrganizationRole::Admin, Organization::factory()->create());
    expect(Gate::forUser($newcomer)->allows('viewHiddenResults', $category))->toBeFalse();

    $category->organization->addMember($newcomer, OrganizationRole::Admin);

    expect(Gate::forUser($newcomer)->allows('viewHiddenResults', $category))->toBeTrue();
});

it('starts afresh on a new request and on a new queue job', function (Closure $nextLifecycle): void {
    $category = hiddenCategory();
    $admin = organizer(OrganizationRole::Admin, $category->organization);
    expect(Gate::forUser($admin)->allows('viewHiddenResults', $category))->toBeTrue();

    // A change that bypasses every hook (raw SQL): only a new request or job may see it.
    DB::table('organization_user')->where('user_id', $admin->id)->update(['role' => OrganizationRole::Staff->value]);
    $nextLifecycle();

    expect(Gate::forUser($admin)->allows('viewHiddenResults', $category))->toBeFalse();
})->with([
    'new HTTP request' => [static fn() => app()->instance('request', Request::create('/next'))],
    'new queue job (scoped instances flushed)' => [static fn() => app()->forgetScopedInstances()],
]);

it('remembers category statuses only while a list renders, and sees a status change inside it', function (): void {
    $category = AwardCategory::factory()->status(CategoryStatus::Draft)->create();
    $nominee = Nominee::factory()->for($category)->create();
    $owner = organizer(OrganizationRole::Owner, $category->organization);
    $staff = organizer(OrganizationRole::Staff, $category->organization);

    PermissionLookups::rememberCategoryStatuses();
    $reads = queriesDuring(static function () use ($staff, $nominee): void {
        foreach (range(1, 5) as $ignored) {
            expect(Gate::forUser($staff)->allows('rename', $nominee))->toBeTrue(); // staff rename: draft only
        }
    });
    expect(array_filter($reads, static fn(string $sql): bool => str_starts_with($sql, 'select "status"')))
        ->toHaveCount(1);

    app(TransitionCategoryStatusAction::class)->execute($category, CategoryStatus::Open, $owner);

    expect(Gate::forUser($staff)->allows('rename', $nominee))->toBeFalse();
    PermissionLookups::forgetCategoryStatuses();
});

it('sees a reveal made earlier in the same list render', function (): void {
    $category = AwardCategory::factory()->status(CategoryStatus::Closed)->create(['closed_at' => now()->subHour()]);
    $nominee = Nominee::factory()->for($category)->create();
    $owner = organizer(OrganizationRole::Owner, $category->organization);

    PermissionLookups::rememberCategoryStatuses();
    expect(Gate::forUser($owner)->allows('updateProfile', $nominee))->toBeTrue(); // until revealed

    app(RevealWinnerAction::class)->execute($category, $owner);

    expect(Gate::forUser($owner)->allows('updateProfile', $nominee))->toBeFalse();
    PermissionLookups::forgetCategoryStatuses();
});

it('reads each category status once when the nominee list renders', function (): void {
    $category = AwardCategory::factory()->status(CategoryStatus::Open)->create();
    Nominee::factory()->count(6)->for($category)->create();
    $owner = organizer(OrganizationRole::Owner, $category->organization);
    $this->actingAs($owner);

    $reads = queriesDuring(
        static fn() => Livewire::test(NomineeManager::class, [
            'program' => $category->awardProgram,
            'category' => $category,
        ])->assertOk(),
    );

    expect(array_filter($reads, static fn(string $sql): bool => str_starts_with($sql, 'select "status"')))
        ->toHaveCount(1);
});
