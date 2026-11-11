<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\TimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeOff>
 */
class TimeOffFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDay()->startOfHour();

        return [
            'business_id' => Business::factory(),
            'user_id' => null,
            'starts_at' => $start,
            'ends_at' => $start->addHours(2),
            'reason' => 'Trámite',
        ];
    }
}
