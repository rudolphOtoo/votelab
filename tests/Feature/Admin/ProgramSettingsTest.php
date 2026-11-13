<?php

declare(strict_types=1);

use App\Actions\Admin\SaveProgramAction;
use App\Actions\Admin\TransitionCategoryStatusAction;
use App\Actions\Voting\FulfillPaidVoteOrderAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\PaymentTrigger;
use App\Enums\ProgramStatus;
use App\Enums\TransactionStatus;
use App\Models\AuditEvent;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\VoteTransaction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(fn() => config(['voting.close_grace_minutes' => 30]));

/**
 * @param  array<string, mixed>  $input
 */
function newProgram(App\Models\User $actor, array $input = []): AwardProgram
{
    return app(SaveProgramAction::class)->create($actor->organizations()->firstOrFail(), $actor, [
        'title' => 'Campus Awards 2027',
        'slug' => 'campus-awards-2027',
        ...$input,
    ]);
}

it('creates a draft program with the maximum grace window by default, audited', function (): void {
    $program = newProgram(organizer());

    expect($program->status)
        ->toBe(ProgramStatus::Draft)
        ->and($program->close_grace_minutes)
        ->toBe(30)
        ->and(AuditEvent::query()->where('action', 'program.created')->exists())
        ->toBeTrue();
});

it('lets owners and admins create programs, but not staff or outsiders', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    $staff = organizer(OrganizationRole::Staff, $organization);

    expect(newProgram(organizer(OrganizationRole::Admin, $organization), ['slug' => 'admin-made'])->exists)
        ->toBeTrue()
        ->and(fn() => app(SaveProgramAction::class)->create($organization, $staff, ['title' => 'X', 'slug' => 'x']))
        ->toThrow(AuthorizationException::class)
        ->and(fn() => app(SaveProgramAction::class)->create($organization, organizer(), [
            'title' => 'X',
            'slug' => 'x',
        ]))
        ->toThrow(AuthorizationException::class);
});

it('keeps the grace window between 10 minutes and the platform maximum', function (int $minutes, bool $valid): void {
    $owner = organizer();
    $program = newProgram($owner);
    $update = fn() => app(SaveProgramAction::class)->update($program, $owner, ['close_grace_minutes' => $minutes]);

    $valid
        ? expect($update()->close_grace_minutes)->toBe($minutes)
        : expect($update)->toThrow(ValidationException::class);
})->with([[9, false], [10, true], [20, true], [30, true], [31, false]]);

it('caps the window at a lower platform maximum', function (): void {
    config(['voting.close_grace_minutes' => 20]);
    $owner = organizer();

    expect(newProgram($owner)->close_grace_minutes)
        ->toBe(20)
        ->and(fn() => app(SaveProgramAction::class)->update(AwardProgram::query()->sole(), $owner, [
            'close_grace_minutes' => 25,
        ]))
        ->toThrow(ValidationException::class);
});

it('locks the grace window while a category is closed but not revealed, and audits changes', function (): void {
    $owner = organizer();
    $program = newProgram($owner);
    $category = AwardCategory::factory()->for($program)->status(CategoryStatus::Open)->create();

    app(SaveProgramAction::class)->update($program, $owner, ['close_grace_minutes' => 15]);
    $changed = AuditEvent::query()->where('action', 'program.updated')->sole();
    expect($changed->before)
        ->toBe(['close_grace_minutes' => 30])
        ->and($changed->after)
        ->toBe(['close_grace_minutes' => 15]);

    $category->forceFill(['status' => CategoryStatus::Closed, 'closed_at' => now()])->save();
    expect(fn() => app(SaveProgramAction::class)->update($program, $owner, [
        'close_grace_minutes' => 20,
    ]))->toThrow(ValidationException::class);

    $category->forceFill(['status' => CategoryStatus::Revealed])->save();
    expect(app(SaveProgramAction::class)->update($program, $owner, [
        'close_grace_minutes' => 20,
    ])->close_grace_minutes)->toBe(20);
});

it('freezes the program\'s window onto a category when it closes, and credits by it', function (): void {
    $gateway = fakeGateway();
    $owner = organizer();
    $program = newProgram($owner);
    app(SaveProgramAction::class)->update($program, $owner, ['close_grace_minutes' => 10]);
    $category = AwardCategory::factory()->for($program)->status(CategoryStatus::Open)->create();

    Carbon::setTestNow('2026-11-20 20:00:00');
    $late = VoteTransaction::factory()->for(Nominee::factory()->for($category))->create();
    $inside = VoteTransaction::factory()->for(Nominee::factory()->for($category))->create();
    $gateway->reports($late->payment_reference, amount: $late->gross_amount);
    $gateway->reports($inside->payment_reference, amount: $inside->gross_amount);

    app(TransitionCategoryStatusAction::class)->execute($category, CategoryStatus::Closed, $owner);
    expect($category->fresh()?->close_grace_minutes)->toBe(10);

    Carbon::setTestNow('2026-11-20 20:09:00');
    app(FulfillPaidVoteOrderAction::class)->execute($inside->payment_reference, PaymentTrigger::Webhook);
    Carbon::setTestNow('2026-11-20 20:11:00');
    app(FulfillPaidVoteOrderAction::class)->execute($late->payment_reference, PaymentTrigger::Webhook);

    expect($inside->fresh()?->status)
        ->toBe(TransactionStatus::Successful)
        ->and($late->fresh()?->status)
        ->toBe(TransactionStatus::PaidUncredited);
});
