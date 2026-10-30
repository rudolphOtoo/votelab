<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Nominee;
use App\Models\VoteRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoteRecord>
 */
final class VoteRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
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
            'vote_transaction_id' => null,
            'is_paid' => false,
            'quantity' => 1,
            'voter_fingerprint' => hash('sha256', fake()->uuid()),
            'ip_hash' => null,
            'cast_at' => now(),
        ];
    }
}
