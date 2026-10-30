<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Exceptions\Voting\CheckoutUnavailableException;
use App\Models\Nominee;
use App\Services\Payment\Data\VotePriceQuote;
use App\Services\Payment\PaystackFeeCalculator;

/**
 * Decides whether a paid-vote order may be placed and prices it. The breakdown shown to the voter
 * (L-8) and the amounts stored and sent to Paystack all come from this one action.
 */
final readonly class QuotePaidVotesAction
{
    public function __construct(
        private PaystackFeeCalculator $fees,
    ) {}

    /**
     * @throws CheckoutUnavailableException
     */
    public function execute(Nominee $nominee, int $quantity): VotePriceQuote
    {
        $category = $nominee->awardCategory()->firstOrFail();
        $program = $category->awardProgram()->firstOrFail();
        $organization = $nominee->organization()->firstOrFail();

        if (!$program->status->isPublic() || !$category->status->isVotingAllowed()) {
            throw CheckoutUnavailableException::votingNotOpen();
        }

        if (!$category->voting_mode->allowsPaidVotes()) {
            throw CheckoutUnavailableException::paidVotesNotAccepted();
        }

        if (!$organization->canTakePaidVotes()) {
            throw CheckoutUnavailableException::paidVotingNotSetUp();
        }

        $maxQuantity = (int) config('voting.max_votes_per_order');

        if ($quantity < 1 || $quantity > $maxQuantity) {
            throw CheckoutUnavailableException::invalidQuantity($maxQuantity);
        }

        return $this->fees->quote($quantity, $category->price_per_vote);
    }
}
