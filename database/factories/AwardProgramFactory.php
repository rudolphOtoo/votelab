<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProgramStatus;
use App\Models\AwardProgram;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AwardProgram>
 */
final class AwardProgramFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(rtrim(fake()->unique()->sentence(3), '.')) . ' Awards';

        return [
            'organization_id' => Organization::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'tagline' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'banner_url' => null,
            'status' => ProgramStatus::Published,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeeks(2),
            'settings' => null,
        ];
    }

    public function draft(): self
    {
        return $this->state(['status' => ProgramStatus::Draft]);
    }
}
