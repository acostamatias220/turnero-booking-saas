<?php

declare(strict_types=1);

namespace App\Booking;

use Carbon\CarbonImmutable;

final readonly class BookingRequest
{
    public function __construct(
        public int $serviceId,
        public CarbonImmutable $startsAt,
        public ?int $staffId,
        public string $customerName,
        public string $customerEmail,
        public ?string $customerPhone = null,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array{service_id: int|string, starts_at: string, staff_id?: int|string|null, name: string, email: string, phone?: string|null, notes?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            serviceId: (int) $data['service_id'],
            startsAt: CarbonImmutable::parse($data['starts_at'])->utc(),
            staffId: isset($data['staff_id']) && $data['staff_id'] !== '' ? (int) $data['staff_id'] : null,
            customerName: trim($data['name']),
            customerEmail: mb_strtolower(trim($data['email'])),
            customerPhone: $data['phone'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }
}
