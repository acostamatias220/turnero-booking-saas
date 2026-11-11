<?php

declare(strict_types=1);

namespace App\Holidays;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Argentine national holidays (feriados) from the public ArgentinaDatos API.
 * Response items look like: {"fecha":"2026-03-24","tipo":"inamovible","nombre":"Día Nacional de la Memoria…"}
 */
final class ArgentinaDatosHolidayProvider implements HolidayProvider
{
    public function __construct(private readonly string $baseUrl) {}

    public function forYear(int $year): array
    {
        $response = Http::acceptJson()
            ->timeout(10)
            ->retry(3, 250, throw: false)
            ->get(rtrim($this->baseUrl, '/')."/{$year}");

        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException("Could not fetch holidays for {$year} (HTTP {$response->status()}).");
        }

        $holidays = [];

        /** @var array<string, mixed> $item */
        foreach ($response->json() as $item) {
            $date = $item['fecha'] ?? null;
            $name = $item['nombre'] ?? null;

            if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && is_string($name)) {
                $holidays[] = ['date' => $date, 'name' => mb_substr($name, 0, 255)];
            }
        }

        return $holidays;
    }
}
