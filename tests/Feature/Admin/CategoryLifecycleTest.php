<?php

declare(strict_types=1);

use App\Actions\Admin\TransitionCategoryStatusAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Events\CategoryStatusChanged;
use App\Exceptions\Admin\StatusChangeRefusedException;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\CategoryStatusTransition;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\User;
use App\Support\LeaderboardCache;
use App\Support\VoteKeys;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * @return array{AwardCategory, User}
 */
function lifecycleCategory(CategoryStatus $status, OrganizationRole $role = OrganizationRole::Admin): array
{
    $organization = Organization::factory()->create(['slug' => 'org']);
    $program = AwardProgram::factory()->for($organization)->create(['slug' => 'awards']);
    $category = AwardCategory::factory()->for($program)->status($status)->create(['slug' => 'cat']);

    return [$category, organizer($role, $organization)];
}

function transition(AwardCategory $category, CategoryStatus $to, User $actor, ?string $reason = null): AwardCategory
{
    return app(TransitionCategoryStatusAction::class)->execute($category, $to, $actor, $reason);
}

it('cannot go straight from Draft to Revealed', function (): void {
    [$category, $admin] = lifecycleCategory(CategoryStatus::Draft);

    expect(fn() => transition($category, CategoryStatus::Revealed, $admin))
        ->toThrow(StatusChangeRefusedException::class);
    expect($category->fresh()?->status)->toBe(CategoryStatus::Draft);
});

it('records who moved the category, from what, to what, when and why', function (): void {
    $audit = captureAuditLog();
    [$category, $admin] = lifecycleCategory(CategoryStatus::Open);

    transition($category, CategoryStatus::Embargoed, $admin, 'Final week: results hidden');

    $row = CategoryStatusTransition::query()->sole();
    expect($row->user_id)
        ->toBe($admin->id)
        ->and($row->from_status)
        ->toBe(CategoryStatus::Open)
        ->and($row->to_status)
        ->toBe(CategoryStatus::Embargoed)
        ->and($row->reason)
        ->toBe('Final week: results hidden')
        ->and($row->created_at)
        ->not
        ->toBeNull()
        ->and($audit->hasInfoThatContains('category.status_changed'))
        ->toBeTrue();

    $entry = collect($audit->getRecords())->firstWhere('message', 'category.status_changed');
    expect($entry?->context['before'])
        ->toBe(['status' => 'open'])
        ->and($entry?->context['after'])
        ->toBe(['status' => 'embargoed']);
});

it('stamps closed_at when voting closes (start of the grace window)', function (): void {
    [$category, $admin] = lifecycleCategory(CategoryStatus::Open);

    expect(transition($category, CategoryStatus::Closed, $admin)->closed_at)->not->toBeNull();
});

it('fires CategoryStatusChanged after the change commits', function (): void {
    Event::fake([CategoryStatusChanged::class]);
    [$category, $admin] = lifecycleCategory(CategoryStatus::Draft);

    transition($category, CategoryStatus::Open, $admin);

    Event::assertDispatched(
        CategoryStatusChanged::class,
        static fn(CategoryStatusChanged $e): bool => (
            $e->from === CategoryStatus::Draft
            && $e->to === CategoryStatus::Open
            && $e->actorUserId === $admin->id
        ),
    );
});

it('clears both cache entries on any change and warms the cache when results go public', function (): void {
    config(['voting.leaderboard_cache_seconds' => 3_600]);
    [$category, $admin] = lifecycleCategory(CategoryStatus::Open);
    Nominee::factory()->for($category)->create(['votes_count' => 9]);
    LeaderboardCache::standings($category);

    transition($category, CategoryStatus::Embargoed, $admin);
    expect(Cache::has(VoteKeys::leaderboard($category->id)))
        ->toBeFalse()
        ->and(Cache::has(VoteKeys::leaderboardFresh($category->id)))
        ->toBeFalse();

    transition($category, CategoryStatus::Open, $admin);
    expect(Cache::has(VoteKeys::leaderboard($category->id)))
        ->toBeTrue()
        ->and(Cache::has(VoteKeys::leaderboardFresh($category->id)))
        ->toBeTrue();
});

it('stops showing counts on public pages once embargoed through the action', function (): void {
    [$category, $admin] = lifecycleCategory(CategoryStatus::Open);
    Nominee::factory()->for($category)->create(['votes_count' => 864_213]);
    $this->get('/org/awards/categories/cat')->assertSee('864,213');

    transition($category, CategoryStatus::Embargoed, $admin);

    $this->get('/org/awards/categories/cat')->assertOk()->assertDontSee('864,213')->assertDontSee('864213');
});

it('refuses staff, and owners of other organizations', function (): void {
    [$category] = lifecycleCategory(CategoryStatus::Open);
    $staff = organizer(OrganizationRole::Staff, $category->organization);

    expect(fn() => transition($category, CategoryStatus::Closed, $staff))
        ->toThrow(AuthorizationException::class)
        ->and(fn() => transition($category, CategoryStatus::Closed, organizer()))
        ->toThrow(AuthorizationException::class);
    expect($category->fresh()?->status)->toBe(CategoryStatus::Open);
});

it('judges the move by the stored status, not the caller\'s stale copy', function (): void {
    [$category, $admin] = lifecycleCategory(CategoryStatus::Open);
    $stale = $category->fresh() ?? $category;
    AwardCategory::query()->whereKey($category->id)->update(['status' => CategoryStatus::Closed, 'closed_at' => now()]);

    // "Embargo" was valid from the copy's Open, but the category is now Closed.
    expect(fn() => transition($stale, CategoryStatus::Embargoed, $admin))->toThrow(AuthorizationException::class);
    expect(CategoryStatusTransition::query()->count())->toBe(0);
});

it('leaves reveals to RevealWinnerAction', function (): void {
    [$category, $admin] = lifecycleCategory(CategoryStatus::Closed);

    expect(fn() => transition($category, CategoryStatus::Revealed, $admin))
        ->toThrow(StatusChangeRefusedException::class);
});
