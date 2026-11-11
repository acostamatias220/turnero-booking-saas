<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'category' => 'barbershop',
            'tagline' => fake()->catchPhrase(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress().', CABA',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'currency' => 'ARS',
            'slot_interval_minutes' => 15,
            'min_notice_minutes' => 0,
            'booking_horizon_days' => 30,
            'cancellation_notice_hours' => 24,
            'deposit_percent' => 0,
            'hold_minutes' => 15,
            'accent_color' => '#6d28d9',
        ];
    }

    public function withDeposit(int $percent = 30): static
    {
        return $this->state(fn () => ['deposit_percent' => $percent]);
    }
}
