<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CategoryStatus;
use App\Enums\VotingMode;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AwardCategory>
 */
final class AwardCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(rtrim(fake()->unique()->sentence(2), '.')) . ' of the Year';

        return [
            'award_program_id' => AwardProgram::factory(),
            'organization_id' => static fn(array $attributes): int => AwardProgram::query()
                ->whereKey($attributes['award_program_id'])
                ->firstOrFail()
                ->organization_id,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'voting_mode' => VotingMode::Paid,
            'price_per_vote' => 100,
            'free_vote_limit' => 1,
            'status' => CategoryStatus::Open,
            'closed_at' => null,
            'display_order' => 0,
        ];
    }

    public function status(CategoryStatus $status): self
    {
        return $this->state([
            'status' => $status,
            'closed_at' => $status === CategoryStatus::Closed ? now() : null,
        ]);
    }

    public function mode(VotingMode $mode): self
    {
        return $this->state(['voting_mode' => $mode]);
    }
}
