<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkingHour>
 */
class WorkingHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'business_id' => fn (array $attributes) => User::query()->findOrFail((int) $attributes['user_id'])->business_id,
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ];
    }
}
