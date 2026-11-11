<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Appointment;
use Carbon\CarbonImmutable;

/**
 * Minimal RFC 5545 generator so customers can add the turno to any calendar app.
 */
final class IcsCalendar
{
    public static function forAppointment(Appointment $appointment, string $status = 'CONFIRMED'): string
    {
        $business = $appointment->business;

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Turnero//Booking//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:'.($status === 'CANCELLED' ? 'CANCEL' : 'PUBLISH'),
            'BEGIN:VEVENT',
            'UID:'.$appointment->public_id.'@turnero',
            'DTSTAMP:'.self::utc(CarbonImmutable::now()),
            'DTSTART:'.self::utc($appointment->starts_at),
            'DTEND:'.self::utc($appointment->ends_at),
            'SUMMARY:'.self::escape("{$appointment->service->name} · {$business->name}"),
            'DESCRIPTION:'.self::escape("Con {$appointment->staff->name}. Código {$appointment->reference()}"),
            'LOCATION:'.self::escape((string) $business->address),
            'STATUS:'.$status,
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    private static function utc(CarbonImmutable $instant): string
    {
        return $instant->utc()->format('Ymd\THis\Z');
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $text);
    }

    /** Lines longer than 75 octets must be folded (RFC 5545 §3.1). */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $chunks = [];

        while (strlen($line) > 75) {
            $cut = 75;

            // Never split a multibyte UTF-8 sequence.
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }

            $chunks[] = substr($line, 0, $cut);
            $line = ' '.substr($line, $cut);
        }

        $chunks[] = $line;

        return implode("\r\n", $chunks);
    }
}
