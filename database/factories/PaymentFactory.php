<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory()->pendingPayment(),
            'business_id' => fn (array $a) => Appointment::acrossTenants()->findOrFail($a['appointment_id'])->business_id,
            'provider' => 'pagosimulado',
            'checkout_id' => 'cs_'.Str::lower((string) Str::ulid()),
            'amount_cents' => 450_000,
            'currency' => 'ARS',
            'status' => PaymentStatus::Pending,
        ];
    }
}
