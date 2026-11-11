<?php

declare(strict_types=1);

namespace App\Booking;

use RuntimeException;

final class SlotUnavailableException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Ese horario acaba de ser reservado. Elegí otro, por favor.');
    }
}
