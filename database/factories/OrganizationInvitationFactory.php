<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * @extends Factory<OrganizationInvitation>
 */
class OrganizationInvitationFactory extends Factory
{
    protected $model = OrganizationInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email' => Str::lower(fake()->unique()->safeEmail()),
            'role' => OrganizationRole::Staff,
            'token_hash' => OrganizationInvitation::hashToken(Str::random(40)),
            'expires_at' => now()->addHours(72),
        ];
    }

    /**
     * Use a known token, so a test can build the link a recipient would get.
     */
    public function withToken(#[SensitiveParameter] string $token): static
    {
        return $this->state(['token_hash' => OrganizationInvitation::hashToken($token)]);
    }
}
