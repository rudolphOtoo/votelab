<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AwardCategory;
use App\Models\Nominee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Nominee>
 */
final class NomineeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'award_category_id' => AwardCategory::factory(),
            'award_program_id' => static fn(array $attributes): int => AwardCategory::query()
                ->whereKey($attributes['award_category_id'])
                ->firstOrFail()
                ->award_program_id,
            'organization_id' => static fn(array $attributes): int => AwardCategory::query()
                ->whereKey($attributes['award_category_id'])
                ->firstOrFail()
                ->organization_id,
            'name' => fake()->name(),
            'stage_name' => null,
            'nominee_code' => 'N' . fake()->unique()->numberBetween(1_000, 999_999),
            'avatar_url' => null,
            'bio' => fake()->sentence(),
            'display_order' => 0,
        ];
    }
}
