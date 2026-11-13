<?php

declare(strict_types=1);

use App\Enums\CategoryPhase;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProgramStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\User;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use Illuminate\Support\Facades\Gate;

require_once __DIR__ . '/approved-matrix-helpers.php';

/**
 * A fixture organization with a published program, one category in the given status, and a nominee.
 *
 * @return array{organization: Organization, program: AwardProgram, category: AwardCategory, nominee: Nominee}
 */
function world(
    CategoryStatus $status = CategoryStatus::Open,
    ProgramStatus $programStatus = ProgramStatus::Published,
): array {
    $organization = Organization::factory()->create();
    $program = AwardProgram::factory()->for($organization)->create(['status' => $programStatus]);
    $category = AwardCategory::factory()->for($program)->status($status)->create();
    $nominee = Nominee::factory()->for($category)->create();

    return compact('organization', 'program', 'category', 'nominee');
}

/**
 * @return array<string, array{string, string, Closure(array): array, list<CategoryStatus>}>
 */
function policyAbilities(): array
{
    return require __DIR__ . '/policy-abilities.php';
}

/**
 * The approved ability for moving a category from one status to another (written independently of
 * AwardCategoryPolicy::abilityForMove), or null when the move isn't allowed at all.
 */
function approvedMove(CategoryStatus $from, CategoryStatus $to): ?string
{
    return (
        [
            'draft>open' => 'category.open',
            'draft>embargoed' => 'category.embargo',
            'open>embargoed' => 'category.embargo',
            'embargoed>open' => 'category.unembargo',
            'open>closed' => 'category.close',
            'embargoed>closed' => 'category.close',
            'closed>revealed' => 'category.reveal',
        ]["{$from->value}>{$to->value}"] ?? null
    );
}

/**
 * @return Generator<string, array{string, string, Closure(array): array, list<CategoryStatus>, CategoryStatus}>
 */
function policyCases(): Generator
{
    foreach (policyAbilities() as $name => [$gate, $ability, $args, $validStatuses]) {
        foreach (CategoryStatus::cases() as $status) {
            yield "{$name} · {$status->value}" => [$gate, $ability, $args, $validStatuses, $status];
        }
    }
}

it('grants each policy ability to exactly the approved roles in each category status', function (
    string $gate,
    string $ability,
    Closure $args,
    array $validStatuses,
    CategoryStatus $status,
): void {
    $world = world($status);
    $arguments = $args($world);

    foreach (OrganizationRole::cases() as $role) {
        $user = User::factory()->create();
        $world['organization']->addMember($user, $role);

        if ($ability === 'move') {
            $move = approvedMove($status, $arguments[1]);
            $expected = $move !== null && approved($move, $role, CategoryPhase::of($status));
        } else {
            $expected =
                in_array($status, $validStatuses, true) && approved($ability, $role, CategoryPhase::of($status));
        }

        expect(Gate::forUser($user)->allows($gate, $arguments))
            ->toBe($expected, "{$gate} as {$role->value} with category {$status->value}");
    }
})->with(policyCases());

it('refuses every ability to an owner of another organization', function (
    string $gate,
    string $ability,
    Closure $args,
    array $validStatuses,
): void {
    $world = world($validStatuses[0]);
    $outsider = organizer(OrganizationRole::Owner); // Owns a different organization.

    expect(Gate::forUser($outsider)->allows($gate, $args($world)))->toBeFalse();
})->with(static function (): Generator {
    foreach (policyAbilities() as $name => $case) {
        yield $name => $case;
    }
});

it('registers no Gate::before or Gate::after override', function (): void {
    $gate = app(Illuminate\Contracts\Auth\Access\Gate::class);
    $before = new ReflectionProperty($gate, 'beforeCallbacks')->getValue($gate);
    $after = new ReflectionProperty($gate, 'afterCallbacks')->getValue($gate);

    expect($before)->toBeEmpty()->and($after)->toBeEmpty();
});

it('never deletes a nominee that has votes or a transaction, for any role', function (string $ledger): void {
    $world = world(CategoryStatus::Open);
    $ledger === 'vote record'
        ? VoteRecord::factory()->for($world['nominee'])->create()
        : VoteTransaction::factory()->for($world['nominee'])->create();

    foreach (OrganizationRole::cases() as $role) {
        expect(Gate::forUser(organizer($role, $world['organization']))->allows(
            'delete',
            $world['nominee'],
        ))->toBeFalse();
    }
})->with(['vote record', 'transaction']);

it('never deletes a program the ledger has touched, even for its owner', function (): void {
    $world = world(CategoryStatus::Open);
    $owner = organizer(OrganizationRole::Owner, $world['organization']);
    expect(Gate::forUser($owner)->allows('delete', $world['program']))->toBeTrue();

    VoteTransaction::factory()->for($world['nominee'])->create();

    expect(Gate::forUser($owner)->allows('delete', $world['program']))->toBeFalse();
});

it('refuses ledger writes to every role, owners included', function (OrganizationRole $role): void {
    $world = world(CategoryStatus::Open);
    $user = organizer($role, $world['organization']);
    $record = VoteRecord::factory()->for($world['nominee'])->create();
    $transaction = VoteTransaction::factory()->for($world['nominee'])->create();
    $gate = Gate::forUser($user);

    expect($gate->allows('create', VoteRecord::class))
        ->toBeFalse()
        ->and($gate->allows('update', $record))
        ->toBeFalse()
        ->and($gate->allows('delete', $record))
        ->toBeFalse()
        ->and($gate->allows('create', VoteTransaction::class))
        ->toBeFalse()
        ->and($gate->allows('update', $transaction))
        ->toBeFalse()
        ->and($gate->allows('delete', $transaction))
        ->toBeFalse()
        ->and($gate->allows('view', $transaction))
        ->toBe($role !== OrganizationRole::Staff);
})->with(OrganizationRole::cases());

it('refuses new categories in an archived program', function (): void {
    $world = world(CategoryStatus::Draft, ProgramStatus::Archived);

    expect(Gate::forUser(organizer(OrganizationRole::Owner, $world['organization']))->allows('create', [
        AwardCategory::class,
        $world['program'],
    ]))->toBeFalse();
});

it('publishes drafts only and unpublishes only while no category is live', function (): void {
    $draft = world(CategoryStatus::Draft, ProgramStatus::Draft);
    $owner = organizer(OrganizationRole::Owner, $draft['organization']);
    expect(Gate::forUser($owner)->allows('publish', $draft['program']))
        ->toBeTrue()
        ->and(Gate::forUser($owner)->allows('unpublish', $draft['program']))
        ->toBeFalse();

    $live = world(CategoryStatus::Embargoed);
    $liveOwner = organizer(OrganizationRole::Owner, $live['organization']);
    expect(Gate::forUser($liveOwner)->allows('unpublish', $live['program']))->toBeFalse();

    $live['category']->forceFill(['status' => CategoryStatus::Closed])->save();
    expect(Gate::forUser($liveOwner)->allows('unpublish', $live['program']))->toBeTrue();
});

it('moves a nominee only between draft categories of the same program', function (): void {
    $world = world(CategoryStatus::Draft);
    $owner = organizer(OrganizationRole::Owner, $world['organization']);
    $otherProgram = AwardCategory::factory()
        ->for(AwardProgram::factory()->for($world['organization']))
        ->status(CategoryStatus::Draft)
        ->create();
    $liveSibling = AwardCategory::factory()->for($world['program'])->status(CategoryStatus::Open)->create();

    expect(Gate::forUser($owner)->allows('changeCategory', [$world['nominee'], $otherProgram]))
        ->toBeFalse()
        ->and(Gate::forUser($owner)->allows('changeCategory', [$world['nominee'], $liveSibling]))
        ->toBeFalse()
        ->and(Gate::forUser($owner)->allows('changeCategory', [$world['nominee'], $world['category']]))
        ->toBeFalse();
});

it('reads the nominee\'s current category status, not a stale model', function (): void {
    $world = world(CategoryStatus::Draft);
    $staff = organizer(OrganizationRole::Staff, $world['organization']);
    $nominee = $world['nominee']->fresh();
    expect(Gate::forUser($staff)->allows('rename', $nominee))->toBeTrue();

    AwardCategory::query()->whereKey($world['category']->id)->update(['status' => CategoryStatus::Open]);

    expect(Gate::forUser($staff)->allows('rename', $nominee))
        ->toBeFalse()
        ->and(Gate::forUser($staff)->allows('updateProfile', $nominee))
        ->toBeTrue();
});
