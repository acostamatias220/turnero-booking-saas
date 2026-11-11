<?php

declare(strict_types=1);

namespace App\Booking;

use App\Enums\AppointmentStatus;
use RuntimeException;

final class InvalidTransitionException extends RuntimeException
{
    public static function between(AppointmentStatus $from, AppointmentStatus $to): self
    {
        return new self("No se puede pasar un turno de «{$from->label()}» a «{$to->label()}».");
    }
}
