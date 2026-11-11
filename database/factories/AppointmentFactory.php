<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a consistent appointment: staff, service and customer share one business.
 *
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $start = CarbonImmutable::now()->addDays(2)->setTime(13, 0);

        return [
            'staff_id' => User::factory(),
            'business_id' => fn (array $a) => User::query()->findOrFail((int) $a['staff_id'])->business_id,
            'service_id' => fn (array $a) => Service::factory()->state(['business_id' => $a['business_id']]),
            'customer_id' => fn (array $a) => Customer::factory()->state(['business_id' => $a['business_id']]),
            'status' => AppointmentStatus::Confirmed,
            'starts_at' => $start,
            'ends_at' => fn (array $a) => CarbonImmutable::parse($a['starts_at'])->addMinutes(30),
            'blocked_until' => fn (array $a) => $a['ends_at'],
            'price_cents' => 1_500_000,
            'deposit_cents' => 0,
            'hold_expires_at' => null,
        ];
    }

    public function at(CarbonImmutable $start, int $minutes = 30, int $buffer = 0): static
    {
        return $this->state(fn () => [
            'starts_at' => $start,
            'ends_at' => $start->addMinutes($minutes),
            'blocked_until' => $start->addMinutes($minutes + $buffer),
        ]);
    }

    public function status(AppointmentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function pendingPayment(?CarbonImmutable $holdUntil = null): static
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::PendingPayment,
            'deposit_cents' => 450_000,
            'hold_expires_at' => $holdUntil ?? CarbonImmutable::now()->addMinutes(15),
        ]);
    }
}
