<?php

declare(strict_types=1);

use App\Actions\Voting\InitiatePaidVoteOrderAction;
use App\Actions\Voting\SummarizeProgramRevenueAction;
use App\Enums\TransactionStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\VoteTransaction;

it('totals vote price, processing fees and gross per program, reconciling with the ledger', function (): void {
    fakeGateway();
    $program = AwardProgram::factory()->create();
    $nominee = Nominee::factory()->for(AwardCategory::factory()->for($program))->create();
    $initiate = app(InitiatePaidVoteOrderAction::class);

    foreach ([25, 10, 3, 100] as $quantity) {
        $initiate->execute($nominee, $quantity);
    }

    [$a, $b, $c, $d] = VoteTransaction::query()->orderBy('id')->get()->all();
    $a->forceFill(['status' => TransactionStatus::Successful])->save();
    $b->forceFill(['status' => TransactionStatus::Successful])->save();
    $c->forceFill(['status' => TransactionStatus::PaidUncredited])->save();
    // $d stays pending.

    // Another program's money must not leak in.
    VoteTransaction::factory()->status(TransactionStatus::Successful)->create();

    $summary = app(SummarizeProgramRevenueAction::class)->execute($program);

    expect($summary->credited->transactions)
        ->toBe(2)
        ->and($summary->credited->votes)
        ->toBe(35)
        ->and($summary->credited->votePrice)
        ->toBe($a->net_amount + $b->net_amount)
        ->and($summary->credited->processingFees)
        ->toBe($a->processing_fee_pesewas + $b->processing_fee_pesewas)
        ->and($summary->credited->gross)
        ->toBe($a->gross_amount + $b->gross_amount)
        ->and($summary->credited->votePrice + $summary->credited->processingFees)
        ->toBe($summary->credited->gross)
        ->and($summary->credited->votePrice)
        ->toBe(3_500)
        ->and($summary->paidUncredited->gross)
        ->toBe($c->gross_amount)
        ->and($summary->countOf(TransactionStatus::Pending))
        ->toBe(1)
        ->and($summary->countOf(TransactionStatus::Expired))
        ->toBe(0)
        ->and(array_sum($summary->transactionsByStatus))
        ->toBe(4);
});

it('returns zeros for a program with no payments', function (): void {
    $summary = app(SummarizeProgramRevenueAction::class)->execute(AwardProgram::factory()->create());

    expect($summary->credited->gross)
        ->toBe(0)
        ->and($summary->paidUncredited->transactions)
        ->toBe(0)
        ->and(array_sum($summary->transactionsByStatus))
        ->toBe(0);
});
