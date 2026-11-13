<?php

declare(strict_types=1);

use App\Actions\Admin\ReorderAction;
use App\Actions\Admin\SaveCategoryAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\VotingMode;
use App\Models\AuditEvent;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function programFor(App\Models\User $user): AwardProgram
{
    return AwardProgram::factory()->for($user->organizations()->firstOrFail())->create();
}

it('rejects a slug already used by another category in the same program', function (): void {
    $owner = organizer();
    $program = programFor($owner);
    $save = app(SaveCategoryAction::class);
    $save->create($program, $owner, ['name' => 'Best Artist', 'slug' => 'best-artist']);

    expect(fn() => $save->create($program, $owner, [
        'name' => 'Best Artist 2',
        'slug' => 'best-artist',
    ]))->toThrow(ValidationException::class);
    expect($save->create(programFor($owner), $owner, [
        'name' => 'Elsewhere',
        'slug' => 'best-artist',
    ])->exists)->toBeTrue();
});

it('defaults the price to 100 pesewas and keeps prices as whole pesewas', function (): void {
    $owner = organizer();
    $category = app(SaveCategoryAction::class)->create(programFor($owner), $owner, ['name' => 'X', 'slug' => 'x']);

    expect($category->price_per_vote)
        ->toBe(100)
        ->and($category->status)
        ->toBe(CategoryStatus::Draft)
        ->and(fn() => app(SaveCategoryAction::class)->update($category, $owner, ['price_per_vote' => 1.5]))
        ->toThrow(ValidationException::class);
});

it('locks slug, mode and price once voting opens, even for owners', function (string $field, mixed $value): void {
    $owner = organizer();
    $category = AwardCategory::factory()->for(programFor($owner))->status(CategoryStatus::Open)->create();

    expect(fn() => app(SaveCategoryAction::class)->update($category, $owner, [
        $field => $value,
    ]))->toThrow(AuthorizationException::class);
})->with([
    ['price_per_vote', 250],
    ['voting_mode', VotingMode::Free->value],
    ['slug', 'new-link'],
    ['free_vote_limit', 3],
]);

it('audits a rename while Live with before and after values', function (): void {
    $owner = organizer();
    $category = AwardCategory::factory()
        ->for(programFor($owner))
        ->status(CategoryStatus::Embargoed)
        ->create(['name' => 'Old Name']);

    app(SaveCategoryAction::class)->update($category, $owner, ['name' => 'New Name']);

    $event = AuditEvent::query()->where('action', 'category.updated')->sole();
    expect($event->before)
        ->toBe(['name' => 'Old Name'])
        ->and($event->after)
        ->toBe(['name' => 'New Name'])
        ->and($event->context)
        ->toBe(['while_live' => true]);
});

it('refuses staff any category edit', function (): void {
    $owner = organizer();
    $program = programFor($owner);
    $staff = organizer(OrganizationRole::Staff, $program->organization);
    $category = AwardCategory::factory()->for($program)->status(CategoryStatus::Draft)->create();

    expect(fn() => app(SaveCategoryAction::class)->create($program, $staff, ['name' => 'X', 'slug' => 'x']))
        ->toThrow(AuthorizationException::class)
        ->and(fn() => app(SaveCategoryAction::class)->update($category, $staff, ['name' => 'Y']))
        ->toThrow(AuthorizationException::class);
});

it('moves categories up and down within their program', function (): void {
    $owner = organizer();
    $program = programFor($owner);
    [$a, $b, $c] = collect(['a', 'b', 'c'])->map(fn(string $s, int $i) => AwardCategory::factory()->for(
        $program,
    )->create(['slug' => $s, 'display_order' => $i]))->all();

    app(ReorderAction::class)->category($c, $owner, -1);
    expect(AwardCategory::query()->orderBy('display_order')->pluck('slug')->all())->toBe(['a', 'c', 'b']);

    app(ReorderAction::class)->category($a, $owner, -1); // Already first: no change.
    expect(AwardCategory::query()->orderBy('display_order')->pluck('slug')->all())->toBe(['a', 'c', 'b']);
});
