<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;

final class AppointmentCancelled
{
    use Dispatchable;

    public function __construct(public readonly Appointment $appointment, public readonly bool $byCustomer) {}
}
