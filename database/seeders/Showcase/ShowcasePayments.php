<?php

declare(strict_types=1);

namespace Database\Seeders\Showcase;

use App\Actions\Reporting\RecordRefundAction;
use App\Enums\TransactionStatus;
use App\Models\AwardCategory;
use App\Models\Nominee;
use App\Models\User;

/**
 * The showcase payments with fixed references that plan/PREVIEW.md points at: one for each result page, a
 * payment still being confirmed when a category closes, and payments confirmed after a reveal (one waiting
 * in the Refunds queue, one refunded).
 */
final readonly class ShowcasePayments
{
    public function __construct(
        private ShowcaseLedger $ledger,
    ) {}

    /**
     * Made while the categories still take votes. The pending one in Sports Personality starts before the
     * close, so the reveal sheet shows it as still being confirmed for the close grace window (30 minutes).
     *
     * @param  array<string, AwardCategory>  $categories
     */
    public function inFlight(array $categories): void
    {
        $artiste = $categories['Artiste of the Year'];

        $this->ledger->credited(self::nominee($artiste, 'KA27'), 5, [
            'payment_reference' => 'VL_SHOWCASE_SUCCESS',
            'voter_email' => 'ama@example.com',
            'created_at' => now()->subMinutes(3),
        ] + ShowcaseLedger::testPhone());

        $this->ledger->checkout(self::nominee($artiste, 'AM42'), 10, TransactionStatus::Pending, [
            'payment_reference' => 'VL_SHOWCASE_PENDING',
            'created_at' => now()->subMinutes(2),
        ]);
        $this->ledger->checkout(self::nominee($artiste, 'YB53'), 20, TransactionStatus::Failed, [
            'payment_reference' => 'VL_SHOWCASE_FAILED',
            'status_reason' => 'gateway_failed',
            'created_at' => now()->subMinutes(6),
        ]);
        $this->ledger->checkout(self::nominee($artiste, 'EA64'), 5, TransactionStatus::Expired, [
            'payment_reference' => 'VL_SHOWCASE_EXPIRED',
            'status_reason' => 'unpaid_timeout',
            'created_at' => now()->subHours(3),
        ]);
        $this->ledger->checkout(self::nominee($categories['Best Dancer'], 'FA55'), 10, TransactionStatus::Flagged, [
            'payment_reference' => 'VL_SHOWCASE_FLAGGED',
            'status_reason' => 'amount_mismatch',
            'verified_at' => now()->subHour(),
            'created_at' => now()->subHour(),
        ]);
        $this->ledger->checkout(
            self::nominee($categories['Sports Personality'], 'KU37'),
            5,
            TransactionStatus::Pending,
            ['payment_reference' => 'VL_SHOWCASE_PENDING_AT_CLOSE', 'created_at' => now()->subMinutes(4)],
        );
    }

    /**
     * Two payments confirmed after the category was revealed: paid, never counted. One waits in the Refunds
     * queue; the other's refund is recorded through the real action (History).
     */
    public function afterTheReveal(AwardCategory $category, User $owner): void
    {
        $late = ['status_reason' => 'category_revealed', 'verified_at' => now(), 'created_at' => now()->subMinute()];

        $this->ledger->checkout(
            self::nominee($category, 'KX24'),
            10,
            TransactionStatus::PaidUncredited,
            $late
            + [
                'payment_reference' => 'VL_SHOWCASE_UNCREDITED',
            ],
        );
        $refunded = $this->ledger->checkout(
            self::nominee($category, 'AR35'),
            5,
            TransactionStatus::PaidUncredited,
            $late + ['payment_reference' => 'VL_SHOWCASE_REFUNDED'],
        );

        app(RecordRefundAction::class)->record(
            $refunded,
            $owner,
            'Paid after the winner was revealed; refunded from the Paystack dashboard.',
            'RF_SHOWCASE_1',
            passwordConfirmed: true,
        );
    }

    private static function nominee(AwardCategory $category, string $code): Nominee
    {
        return $category->nominees()->where('nominee_code', $code)->sole();
    }
}
