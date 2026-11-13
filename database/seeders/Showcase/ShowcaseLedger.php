<?php

declare(strict_types=1);

namespace Database\Seeders\Showcase;

use App\Enums\TransactionStatus;
use App\Enums\VotingMode;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\VoteRecord;
use App\Models\VoteTransaction;
use App\Services\Payment\PaystackFeeCalculator;
use App\Services\Security\PhoneHasher;
use Illuminate\Support\Carbon;

/**
 * Votes and payments for the local showcase, recorded the way production records them: each credited checkout
 * is a successful transaction with its ledger row (vote_records), free votes are one-vote ledger rows, and
 * counters are set from the ledger (syncCounters), so there is never drift.
 */
final class ShowcaseLedger
{
    /** The Paystack test mobile-money number: the admin can look its payments up by phone. */
    public const string TEST_PHONE = '0551234987';

    /** Paid votes are split into checkouts of these sizes, largest first. */
    private const array CHECKOUT_SIZES = [100, 50, 20, 10, 5, 1];

    private int $checkouts = 0;

    /**
     * A nominee's credited votes: in free and hybrid categories a share as free votes, the rest as paid
     * checkouts, plus one failed checkout wherever votes can be paid.
     */
    public function votes(Nominee $nominee, int $votes): void
    {
        $mode = $nominee->awardCategory->voting_mode;
        $free = match ($mode) {
            VotingMode::Free => $votes,
            VotingMode::Hybrid => intdiv($votes, 3),
            VotingMode::Paid => 0,
        };

        for ($i = 0; $i < $free; $i++) {
            VoteRecord::factory()->for($nominee)->create(self::owners($nominee) + ['cast_at' => self::pastMoment()]);
        }

        $paid = $votes - $free;

        foreach (self::CHECKOUT_SIZES as $size) {
            for (; $paid >= $size; $paid -= $size) {
                $this->credited($nominee, $size);
            }
        }

        if ($votes > 0 && $mode->allowsPaidVotes()) {
            $this->checkout($nominee, 5, TransactionStatus::Failed, ['created_at' => self::pastMoment()]);
        }
    }

    /**
     * A successful checkout and its ledger row.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function credited(Nominee $nominee, int $quantity, array $attributes = []): VoteTransaction
    {
        $at = $attributes['created_at'] ?? self::pastMoment();
        $transaction = $this->checkout(
            $nominee,
            $quantity,
            TransactionStatus::Successful,
            $attributes
            + [
                'created_at' => $at,
                'verified_at' => $at,
                'credited_at' => $at,
            ],
        );

        VoteRecord::factory()->for($nominee)->create(
            self::owners($nominee)
            + [
                'vote_transaction_id' => $transaction->id,
                'is_paid' => true,
                'quantity' => $quantity,
                'voter_fingerprint' => null,
                'cast_at' => $transaction->credited_at,
            ],
        );

        return $transaction;
    }

    /**
     * A checkout with the real fee quote. Every third is paid from the Paystack test number and every fourth
     * carries a receipt email (on example.com, which can't receive mail).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function checkout(
        Nominee $nominee,
        int $quantity,
        TransactionStatus $status,
        array $attributes = [],
    ): VoteTransaction {
        $quote = PaystackFeeCalculator::fromConfig()->quote(
            quantity: $quantity,
            unitPrice: $nominee->awardCategory->price_per_vote,
        );
        $this->checkouts++;
        $phone = ($this->checkouts % 3) === 0 ? self::testPhone() : [];

        return VoteTransaction::factory()
            ->for($nominee)
            ->status($status)
            ->create(
                $attributes
                + $phone
                + self::owners($nominee)
                + [
                    'quantity' => $quote->quantity,
                    'unit_price' => $quote->unitPrice,
                    'net_amount' => $quote->subtotal,
                    'processing_fee_pesewas' => $quote->processingFee,
                    'gross_amount' => $quote->total,
                    'settlement_subaccount_code' => $nominee->organization->paystack_subaccount_code,
                    'voter_email' => ($this->checkouts % 4) === 0 ? "voter{$this->checkouts}@example.com" : null,
                ],
            );
    }

    /**
     * @return array<string, string>  phone_hash and phone_last3 for the Paystack test number
     */
    public static function testPhone(): array
    {
        return app(PhoneHasher::class)->digest(self::TEST_PHONE) ?? [];
    }

    /**
     * Every nominee's counter from the ledger.
     */
    public static function syncCounters(AwardProgram $program): void
    {
        $program
            ->nominees()
            ->each(static function (Nominee $nominee): void {
                $nominee->forceFill(['votes_count' => (int) $nominee->voteRecords()->sum('quantity')])->save();
            });
    }

    /**
     * The nominee's organization, program and category ids, given up front so the factories don't look the
     * nominee up again for every row.
     *
     * @return array{organization_id: int, award_program_id: int, award_category_id: int}
     */
    private static function owners(Nominee $nominee): array
    {
        return [
            'organization_id' => $nominee->organization_id,
            'award_program_id' => $nominee->award_program_id,
            'award_category_id' => $nominee->award_category_id,
        ];
    }

    /**
     * Spread over the three days the program has been open, but never in the last 10 minutes.
     */
    private static function pastMoment(): Carbon
    {
        return now()->subMinutes(fake()->numberBetween(10, 3 * 24 * 60));
    }
}
