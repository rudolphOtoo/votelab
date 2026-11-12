<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Two-factor turned on and confirmed with this authenticator seed (base32) and these recovery codes.
     *
     * @param  list<string>  $recoveryCodes
     */
    public function withTwoFactor(string $seed = 'JBSWY3DPEHPK3PXP', array $recoveryCodes = ['recovery-code-1']): static
    {
        return $this->state([
            'two_factor_secret' => encrypt($seed),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes, JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(static fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
