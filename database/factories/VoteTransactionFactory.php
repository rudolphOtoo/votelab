<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TransactionStatus;
use App\Models\Nominee;
use App\Models\VoteTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Amounts follow the plan's model: net = quantity x unit price (owed to the organizer),
 * fee = gateway fee passed on to the voter, gross = net + fee (charged to the voter).
 *
 * @extends Factory<VoteTransaction>
 */
final class VoteTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 100);
        $unitPrice = 100;
        $net = $quantity * $unitPrice;
        $fee = intdiv($net * 2, 100);

        return [
            'nominee_id' => Nominee::factory(),
            'award_category_id' => static fn(array $attributes): int => Nominee::query()
                ->whereKey($attributes['nominee_id'])
                ->firstOrFail()
                ->award_category_id,
            'award_program_id' => static fn(array $attributes): int => Nominee::query()
                ->whereKey($attributes['nominee_id'])
                ->firstOrFail()
                ->award_program_id,
            'organization_id' => static fn(array $attributes): int => Nominee::query()
                ->whereKey($attributes['nominee_id'])
                ->firstOrFail()
                ->organization_id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'gross_amount' => $net + $fee,
            'processing_fee_pesewas' => $fee,
            'net_amount' => $net,
            'currency' => 'GHS',
            'payment_provider' => 'paystack',
            'payment_reference' => 'VL_' . Str::upper(Str::random(20)),
            'status' => TransactionStatus::Pending,
            'voter_email' => null,
            'metadata' => null,
        ];
    }

    public function status(TransactionStatus $status): self
    {
        return $this->state(['status' => $status]);
    }
}
