<?php

declare(strict_types=1);

use App\Enums\TransactionStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use Illuminate\Database\QueryException;

it('keeps denormalized organization and program ids consistent through factories', function (): void {
    $transaction = VoteTransaction::factory()->create();
    $nominee = $transaction->nominee;

    expect($transaction->award_category_id)
        ->toBe($nominee->award_category_id)
        ->and($transaction->award_program_id)
        ->toBe($nominee->award_program_id)
        ->and($transaction->organization_id)
        ->toBe($nominee->organization_id)
        ->and($nominee->awardCategory->award_program_id)
        ->toBe($nominee->award_program_id)
        ->and($nominee->awardProgram->organization_id)
        ->toBe($nominee->organization_id);
});

it('starts every nominee at zero votes and keeps the counter out of mass assignment', function (): void {
    $nominee = Nominee::factory()->create();
    $nominee->fill(['votes_count' => 500])->save();

    expect($nominee->fresh()?->votes_count)->toBe(0);
});

it('rejects duplicate category slugs within a program', function (): void {
    $program = AwardProgram::factory()->create();
    AwardCategory::factory()->for($program)->create(['slug' => 'best-dressed']);

    AwardCategory::factory()->for($program)->create(['slug' => 'best-dressed']);
})->throws(QueryException::class);

it('allows the same category slug in different programs', function (): void {
    AwardCategory::factory()->create(['slug' => 'best-dressed']);
    AwardCategory::factory()->create(['slug' => 'best-dressed']);

    expect(AwardCategory::query()->where('slug', 'best-dressed')->count())->toBe(2);
});

it('rejects duplicate nominee codes within a program, even across categories', function (): void {
    $program = AwardProgram::factory()->create();
    $first = AwardCategory::factory()->for($program)->create();
    $second = AwardCategory::factory()->for($program)->create();
    Nominee::factory()->for($first)->create(['nominee_code' => 'C01-01']);

    Nominee::factory()->for($second)->create(['nominee_code' => 'C01-01']);
})->throws(QueryException::class);

it('rejects duplicate payment references', function (): void {
    VoteTransaction::factory()->create(['payment_reference' => 'VL_DUPLICATE']);
    VoteTransaction::factory()->create(['payment_reference' => 'VL_DUPLICATE']);
})->throws(QueryException::class);

it('refuses to record the same paid transaction twice in the ledger', function (): void {
    $transaction = VoteTransaction::factory()->status(TransactionStatus::Successful)->create();
    $attributes = ['vote_transaction_id' => $transaction->id, 'is_paid' => true, 'quantity' => $transaction->quantity];

    VoteRecord::factory()->for($transaction->nominee)->create($attributes);
    VoteRecord::factory()->for($transaction->nominee)->create($attributes);
})->throws(QueryException::class);

it('allows any number of free votes without a transaction', function (): void {
    $nominee = Nominee::factory()->create();

    VoteRecord::factory()->count(3)->for($nominee)->create();

    expect($nominee->voteRecords()->count())->toBe(3);
});

it('casts money columns to integers', function (): void {
    $transaction = VoteTransaction::factory()->create()->fresh();

    expect($transaction?->gross_amount)
        ->toBeInt()
        ->and($transaction?->fee_amount)
        ->toBeInt()
        ->and($transaction?->net_amount)
        ->toBeInt()
        ->and($transaction?->gross_amount)
        ->toBe($transaction?->net_amount + $transaction?->fee_amount);
});
