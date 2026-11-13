<?php

declare(strict_types=1);

use App\Enums\TransactionStatus;
use App\Enums\VotingMode;

it('knows which voting modes accept free and paid votes', function (): void {
    expect(VotingMode::Free->allowsFreeVotes())
        ->toBeTrue()
        ->and(VotingMode::Free->allowsPaidVotes())
        ->toBeFalse()
        ->and(VotingMode::Paid->allowsFreeVotes())
        ->toBeFalse()
        ->and(VotingMode::Paid->allowsPaidVotes())
        ->toBeTrue()
        ->and(VotingMode::Hybrid->allowsFreeVotes())
        ->toBeTrue()
        ->and(VotingMode::Hybrid->allowsPaidVotes())
        ->toBeTrue();
});

it('keeps only pending and expired transactions open to a successful payment', function (): void {
    foreach (TransactionStatus::cases() as $status) {
        expect($status->awaitsPayment())
            ->toBe(in_array($status, [TransactionStatus::Pending, TransactionStatus::Expired], true), $status->value);
    }
});

it('flags paid-but-uncredited and flagged transactions for organizer attention', function (): void {
    expect(TransactionStatus::PaidUncredited->needsAttention())
        ->toBeTrue()
        ->and(TransactionStatus::Flagged->needsAttention())
        ->toBeTrue()
        ->and(TransactionStatus::Successful->needsAttention())
        ->toBeFalse()
        ->and(TransactionStatus::Pending->needsAttention())
        ->toBeFalse();
});
