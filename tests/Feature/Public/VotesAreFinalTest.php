<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Enums\VotingMode;
use App\Livewire\Public\VoteDrawer;
use App\Models\AwardCategory;
use App\Models\Nominee;
use Livewire\Livewire;

// D-134: voters are told before they vote that counted votes are final (refunds only for payments that were
// received but not counted, D-98).

function finalVotesNominee(VotingMode $mode): Nominee
{
    $category = AwardCategory::factory()->status(CategoryStatus::Open)->mode($mode)->create(['price_per_vote' => 100]);

    return Nominee::factory()->for($category)->create();
}

it('says beside Pay that counted votes are final and not refundable', function (): void {
    fakeGateway();

    Livewire::test(VoteDrawer::class, ['nomineeId' => finalVotesNominee(VotingMode::Paid)->id])->assertSeeInOrder(
        ['securely with Paystack', "Votes are final once counted and can't be refunded."],
        escape: false,
    );
});

it('says on the free-vote confirm that votes are final, without mentioning refunds', function (): void {
    fakeGateway();

    Livewire::test(VoteDrawer::class, ['nomineeId' => finalVotesNominee(VotingMode::Free)->id])
        ->assertSeeInOrder(['Cast my free vote', 'Votes are final once counted.'])
        ->assertDontSee('refunded');
});

it('shows the line for the chosen tab when a category takes both kinds of vote', function (): void {
    fakeGateway();

    Livewire::test(VoteDrawer::class, ['nomineeId' => finalVotesNominee(VotingMode::Hybrid)->id])
        ->assertSee("can't be refunded", escape: false)
        ->call('showTab', 'free')
        ->assertSee('Votes are final once counted.')
        ->assertDontSee("can't be refunded", escape: false);
});
