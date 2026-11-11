<?php

declare(strict_types=1);

namespace App\Holidays;

interface HolidayProvider
{
    /**
     * @return list<array{date: string, name: string}>
     */
    public function forYear(int $year): array;
}
