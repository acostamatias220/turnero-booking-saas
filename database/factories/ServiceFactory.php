<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->randomElement(['Corte de pelo', 'Corte + barba', 'Sesión de kinesiología', 'Clase de pilates', 'Manicura']),
            'description' => fake()->sentence(),
            'duration_minutes' => 30,
            'buffer_minutes' => 0,
            'price_cents' => 1_500_000,
            'is_active' => true,
        ];
    }
}
