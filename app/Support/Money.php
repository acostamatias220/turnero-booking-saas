<?php

declare(strict_types=1);

namespace App\Support;

use NumberFormatter;

final class Money
{
    public static function format(int $cents, string $currency = 'ARS'): string
    {
        $formatter = new NumberFormatter('es_AR', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $cents % 100 === 0 ? 0 : 2);

        return (string) $formatter->formatCurrency($cents / 100, $currency);
    }
}
