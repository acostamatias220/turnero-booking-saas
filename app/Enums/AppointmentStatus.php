<?php

declare(strict_types=1);

namespace App\Enums;

enum AppointmentStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Esperando seña',
            self::Confirmed => 'Confirmado',
            self::Completed => 'Completado',
            self::NoShow => 'No asistió',
            self::Cancelled => 'Cancelado',
            self::Expired => 'Expirado',
        };
    }

    /** Statuses that occupy the professional's time. */
    public function isActive(): bool
    {
        return in_array($this, [self::PendingPayment, self::Confirmed], true);
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::PendingPayment => [self::Confirmed, self::Cancelled, self::Expired],
            self::Confirmed => [self::Completed, self::NoShow, self::Cancelled],
            self::Expired => [self::Confirmed], // late payment that could re-reserve its slot
            self::Completed, self::NoShow, self::Cancelled => [],
        }, true);
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [self::PendingPayment->value, self::Confirmed->value];
    }
}
