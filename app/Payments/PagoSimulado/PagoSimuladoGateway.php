<?php

declare(strict_types=1);

namespace App\Payments\PagoSimulado;

use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Payments\CheckoutSession;
use App\Payments\PaymentGateway;
use Illuminate\Support\Str;

/**
 * Sandbox provider that behaves like a hosted checkout (Mercado Pago Checkout Pro,
 * Stripe Checkout): we redirect the customer away, and the result arrives later
 * through a signed, possibly duplicated, possibly late webhook.
 */
final class PagoSimuladoGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'pagosimulado';
    }

    public function createCheckout(Appointment $appointment): CheckoutSession
    {
        $checkoutId = 'cs_'.Str::lower((string) Str::ulid());

        Payment::query()->create([
            'appointment_id' => $appointment->id,
            'provider' => $this->name(),
            'checkout_id' => $checkoutId,
            'amount_cents' => $appointment->deposit_cents,
            'currency' => $appointment->business->currency,
            'status' => PaymentStatus::Pending,
        ]);

        return new CheckoutSession($checkoutId, route('pagosimulado.checkout', $checkoutId));
    }

    public function refund(Payment $payment): void
    {
        $payment->status = PaymentStatus::Refunded;
        $payment->save();
    }
}
