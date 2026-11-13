<?php

declare(strict_types=1);

use App\Actions\Reporting\FindTransactionsAction;
use App\Enums\OrganizationRole;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\VoteTransaction;
use App\Services\Security\PhoneHasher;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * @return array{AwardProgram, VoteTransaction}
 */
function programWithTransaction(): array
{
    config(['voting.phone_hash_key' => 'lookup-test-key']);
    $category = AwardCategory::factory()->create();
    $transaction = VoteTransaction::factory()->for(Nominee::factory()->for($category))->create([
        'voter_email' => 'kwame.asante@gmail.com',
        ...(app(PhoneHasher::class)->digest('0241234567') ?? []),
    ]);

    return [$category->awardProgram, $transaction];
}

it('lists recent transactions with masked email and phone', function (): void {
    [$program, $transaction] = programWithTransaction();
    $admin = organizer(OrganizationRole::Admin, $program->organization);

    $row = app(FindTransactionsAction::class)->recent($program, $admin)[0];

    expect($row->reference)
        ->toBe($transaction->payment_reference)
        ->and($row->email)
        ->toBe('k***@gmail.com')
        ->and($row->phone)
        ->toBe('*******567');
});

it('finds by exact phone through the hash, shows full details and audits without the number', function (string $typed): void {
    $audit = captureAuditLog();
    [$program, $transaction] = programWithTransaction();
    $owner = organizer(OrganizationRole::Owner, $program->organization);

    $rows = app(FindTransactionsAction::class)->lookup($program, $owner, $typed);

    expect($rows)
        ->toHaveCount(1)
        ->and($rows[0]->id)
        ->toBe($transaction->id)
        ->and($rows[0]->phone)
        ->toBe('233241234567')
        ->and($rows[0]->email)
        ->toBe('kwame.asante@gmail.com');

    $entry = collect($audit->getRecords())->firstWhere('message', 'transactions.lookup');
    expect($entry?->context)
        ->toMatchArray(['kind' => 'phone', 'matches' => 1])
        ->and(json_encode($entry?->context, JSON_THROW_ON_ERROR))
        ->not->toContain('241234567');
})->with(['0241234567', '+233 24 123 4567', '233241234567']);

it('finds by exact email and by exact reference, and audits each lookup', function (): void {
    $audit = captureAuditLog();
    [$program, $transaction] = programWithTransaction();
    $admin = organizer(OrganizationRole::Admin, $program->organization);
    $find = app(FindTransactionsAction::class);

    expect($find->lookup($program, $admin, 'KWAME.ASANTE@gmail.com'))
        ->toHaveCount(1)
        ->and($find->lookup($program, $admin, $transaction->payment_reference))
        ->toHaveCount(1)
        ->and($find->lookup($program, $admin, 'kwame'))
        ->toBeEmpty();

    expect(collect($audit->getRecords())->where('message', 'transactions.lookup')->pluck('context.kind')->all())->toBe([
        'email',
        'reference',
        'reference',
    ]);
});

it('never returns another program\'s transactions', function (): void {
    [, $transaction] = programWithTransaction();
    $otherProgram = AwardProgram::factory()->for($transaction->awardProgram->organization)->create();
    $owner = organizer(OrganizationRole::Owner, $otherProgram->organization);

    expect(app(FindTransactionsAction::class)->lookup(
        $otherProgram,
        $owner,
        $transaction->payment_reference,
    ))->toBeEmpty();
});

it('refuses staff and outsiders', function (): void {
    [$program] = programWithTransaction();

    expect(fn() => app(FindTransactionsAction::class)->recent($program, organizer(
        OrganizationRole::Staff,
        $program->organization,
    )))
        ->toThrow(AuthorizationException::class)
        ->and(fn() => app(FindTransactionsAction::class)->lookup($program, organizer(), 'x@example.com'))
        ->toThrow(AuthorizationException::class);
});

it('stops matching old rows if the phone hash key is rotated (documented)', function (): void {
    [$program] = programWithTransaction();
    config(['voting.phone_hash_key' => 'a-different-key']);

    expect(app(FindTransactionsAction::class)->lookup(
        $program,
        organizer(OrganizationRole::Owner, $program->organization),
        '0241234567',
    ))->toBeEmpty();
});
