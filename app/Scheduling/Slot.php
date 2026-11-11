<?php

declare(strict_types=1);

namespace App\Scheduling;

use Carbon\CarbonImmutable;
use JsonSerializable;

final readonly class Slot implements JsonSerializable
{
    /**
     * @param  list<int>  $staffIds  professionals free at this time
     */
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public array $staffIds,
        public string $timezone,
    ) {}

    /**
     * @return array{starts_at: string, ends_at: string, time: string, staff_ids: list<int>}
     */
    public function jsonSerialize(): array
    {
        return [
            'starts_at' => $this->startsAt->toIso8601ZuluString(),
            'ends_at' => $this->endsAt->toIso8601ZuluString(),
            'time' => $this->startsAt->setTimezone($this->timezone)->format('H:i'),
            'staff_ids' => $this->staffIds,
        ];
    }
}
