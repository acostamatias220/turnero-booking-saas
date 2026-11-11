<?php

declare(strict_types=1);

namespace App\Booking;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;

final class StaffStatusChanger
{
    public function __construct(private readonly AppointmentLifecycle $lifecycle) {}

    public function change(Appointment $appointment, AppointmentStatus $status): void
    {
        match ($status) {
            AppointmentStatus::Completed => $this->lifecycle->markCompleted($appointment),
            AppointmentStatus::NoShow => $this->lifecycle->markNoShow($appointment),
            AppointmentStatus::Cancelled => $this->lifecycle->cancel($appointment, 'business'),
            default => throw InvalidTransitionException::between($appointment->status, $status),
        };
    }
}
