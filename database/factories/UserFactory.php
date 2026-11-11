<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'role' => Role::Owner,
            'is_bookable' => true,
            'name' => fake()->name(),
            'title' => null,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }

    public function staff(): static
    {
        return $this->state(fn (array $attributes) => ['role' => Role::Staff]);
    }

    public function forBusiness(Business $business): static
    {
        return $this->state(fn (array $attributes) => ['business_id' => $business->id]);
    }
}
