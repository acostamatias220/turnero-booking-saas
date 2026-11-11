<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\Appointment;
use App\Models\Payment;

interface PaymentGateway
{
    public function name(): string;

    /** Start a hosted checkout for the appointment's deposit. */
    public function createCheckout(Appointment $appointment): CheckoutSession;

    public function refund(Payment $payment): void;
}
