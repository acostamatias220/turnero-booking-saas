<?php

declare(strict_types=1);

namespace App\Booking;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Business;
use App\Payments\PaymentGateway;

/**
 * Book + (optionally) start the deposit checkout. Shared by the web booking page and the public API.
 */
final class BookingCheckout
{
    public function __construct(
        private readonly BookingService $booking,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * @return array{appointment: Appointment, checkout_url: string|null}
     */
    public function handle(Business $business, BookingRequest $request): array
    {
        $appointment = $this->booking->book($business, $request);
        $appointment->setRelation('business', $business);

        $checkoutUrl = $appointment->status === AppointmentStatus::PendingPayment
            ? $this->gateway->createCheckout($appointment)->url
            : null;

        return ['appointment' => $appointment, 'checkout_url' => $checkoutUrl];
    }
}
