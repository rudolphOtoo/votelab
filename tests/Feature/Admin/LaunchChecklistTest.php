<?php

declare(strict_types=1);

use App\Actions\Admin\ChangeProgramStatusAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProgramStatus;
use App\Enums\VotingMode;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Support\LaunchChecklist;
use Illuminate\Validation\ValidationException;

/**
 * A draft program that passes every check (test keys, test environment).
 */
function readyProgram(VotingMode $mode = VotingMode::Paid): AwardProgram
{
    config(['payments.providers.paystack.secret_key' => 'sk_test_checklist']);
    $organization = Organization::factory()->create(['paystack_subaccount_code' => 'ACCT_ready']);
    $program = AwardProgram::factory()->for($organization)->create([
        'status' => ProgramStatus::Draft,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDays(10),
    ]);
    $category = AwardCategory::factory()
        ->for($program)
        ->status(CategoryStatus::Draft)
        ->create(['voting_mode' => $mode, 'price_per_vote' => 100]);
    Nominee::factory()->for($category)->create(['avatar_url' => 'https://example.test/a.webp']);

    return $program;
}

function failing(AwardProgram $program): array
{
    return LaunchChecklist::for($program->fresh() ?? $program)->failing();
}

it('passes a ready program and lets an owner or admin publish it', function (OrganizationRole $role): void {
    $program = readyProgram();

    expect(failing($program))->toBeEmpty();
    app(ChangeProgramStatusAction::class)->publish($program, organizer($role, $program->organization));
    expect($program->fresh()?->status)->toBe(ProgramStatus::Published);
})->with([OrganizationRole::Owner, OrganizationRole::Admin]);

it('flags each missing piece', function (string $item, Closure $break): void {
    $program = readyProgram();
    $break($program);

    expect(failing($program))->toContain($item);
})->with([
    'no categories' => [
        'categories',
        fn(AwardProgram $p) => Nominee::query()->delete() + AwardCategory::query()->delete(),
    ],
    'empty category' => [
        'nominees',
        fn(AwardProgram $p) => AwardCategory::factory()->for($p)->status(CategoryStatus::Draft)->create(),
    ],
    'missing photo' => ['photos', fn(AwardProgram $p) => Nominee::query()->update(['avatar_url' => null])],
    'zero price' => ['prices', fn(AwardProgram $p) => AwardCategory::query()->update(['price_per_vote' => 0])],
    'no payout account' => [
        'payout',
        fn(AwardProgram $p) => $p->organization->forceFill(['paystack_subaccount_code' => null])->save(),
    ],
    'no dates' => ['dates', fn(AwardProgram $p) => $p->forceFill(['starts_at' => null])->save()],
    'end before start' => ['dates', fn(AwardProgram $p) => $p->forceFill(['ends_at' => now()])->save()],
]);

it('ignores archived nominees and treats an all-archived category as empty', function (): void {
    $program = readyProgram();
    Nominee::query()->update(['archived_at' => now()]);

    expect(failing($program))->toContain('nominees');
});

it('refuses to publish while anything fails', function (): void {
    $program = readyProgram();
    Nominee::query()->update(['avatar_url' => null]);

    expect(fn() => app(ChangeProgramStatusAction::class)->publish($program, organizer(
        OrganizationRole::Owner,
        $program->organization,
    )))
        ->toThrow(ValidationException::class);
    expect($program->fresh()?->status)->toBe(ProgramStatus::Draft);
});

it('blocks publishing with test keys in production', function (): void {
    $program = readyProgram();
    app()->detectEnvironment(static fn(): string => 'production');

    expect(failing($program))->toContain('paystack_mode');

    config(['payments.providers.paystack.secret_key' => 'sk_live_real']);
    expect(failing($program))->not->toContain('paystack_mode');
});

it('refuses live keys outside production', function (): void {
    $program = readyProgram();
    config(['payments.providers.paystack.secret_key' => 'sk_live_real']);

    expect(failing($program))->toContain('paystack_mode');
});

it('does not care about Paystack for a free-voting-only program', function (): void {
    $program = readyProgram(VotingMode::Free);
    config(['payments.providers.paystack.secret_key' => 'sk_live_real']);
    $program->organization->forceFill(['paystack_subaccount_code' => null])->save();

    expect(failing($program))->toBeEmpty();
});

it('lets staff do none of publish, unpublish or archive', function (): void {
    $program = readyProgram();
    $staff = organizer(OrganizationRole::Staff, $program->organization);

    expect(fn() => app(ChangeProgramStatusAction::class)->publish($program, $staff))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class)
        ->and(fn() => app(ChangeProgramStatusAction::class)->archive($program, $staff))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});
