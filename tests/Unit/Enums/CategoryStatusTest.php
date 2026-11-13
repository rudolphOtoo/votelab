<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;

dataset('allowed transitions', [
    'draft → open' => [CategoryStatus::Draft, CategoryStatus::Open],
    'draft → embargoed' => [CategoryStatus::Draft, CategoryStatus::Embargoed],
    'open → embargoed' => [CategoryStatus::Open, CategoryStatus::Embargoed],
    'open → closed' => [CategoryStatus::Open, CategoryStatus::Closed],
    'embargoed → open' => [CategoryStatus::Embargoed, CategoryStatus::Open],
    'embargoed → closed' => [CategoryStatus::Embargoed, CategoryStatus::Closed],
    'closed → revealed' => [CategoryStatus::Closed, CategoryStatus::Revealed],
]);

it('allows the documented transitions', function (CategoryStatus $from, CategoryStatus $to): void {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with('allowed transitions');

it('forbids every other transition', function (): void {
    $allowed = [
        'draft' => ['open', 'embargoed'],
        'open' => ['embargoed', 'closed'],
        'embargoed' => ['open', 'closed'],
        'closed' => ['revealed'],
        'revealed' => [],
    ];

    foreach (CategoryStatus::cases() as $from) {
        foreach (CategoryStatus::cases() as $to) {
            expect($from->canTransitionTo($to))
                ->toBe(in_array($to->value, $allowed[$from->value], true), "{$from->value} → {$to->value}");
        }
    }
});

it('cannot jump from draft straight to revealed', function (): void {
    expect(CategoryStatus::Draft->canTransitionTo(CategoryStatus::Revealed))->toBeFalse();
});

it('accepts new votes only while open or embargoed', function (): void {
    expect(CategoryStatus::Draft->isVotingAllowed())
        ->toBeFalse()
        ->and(CategoryStatus::Open->isVotingAllowed())
        ->toBeTrue()
        ->and(CategoryStatus::Embargoed->isVotingAllowed())
        ->toBeTrue()
        ->and(CategoryStatus::Closed->isVotingAllowed())
        ->toBeFalse()
        ->and(CategoryStatus::Revealed->isVotingAllowed())
        ->toBeFalse();
});

it('shows the leaderboard publicly only while open or revealed', function (): void {
    expect(CategoryStatus::Draft->isLeaderboardPublic())
        ->toBeFalse()
        ->and(CategoryStatus::Open->isLeaderboardPublic())
        ->toBeTrue()
        ->and(CategoryStatus::Embargoed->isLeaderboardPublic())
        ->toBeFalse()
        ->and(CategoryStatus::Closed->isLeaderboardPublic())
        ->toBeFalse()
        ->and(CategoryStatus::Revealed->isLeaderboardPublic())
        ->toBeTrue();
});

it('can still credit verified payments while open, embargoed or within the close grace window', function (): void {
    expect(CategoryStatus::Draft->canFulfillPayments())
        ->toBeFalse()
        ->and(CategoryStatus::Open->canFulfillPayments())
        ->toBeTrue()
        ->and(CategoryStatus::Embargoed->canFulfillPayments())
        ->toBeTrue()
        ->and(CategoryStatus::Closed->canFulfillPayments())
        ->toBeTrue()
        ->and(CategoryStatus::Revealed->canFulfillPayments())
        ->toBeFalse();
});

it('has a label for every case', function (): void {
    foreach (CategoryStatus::cases() as $status) {
        expect($status->label())->toBeString()->not->toBeEmpty();
    }
});
