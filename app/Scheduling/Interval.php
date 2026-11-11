<?php

declare(strict_types=1);

namespace App\Scheduling;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Half-open time interval [start, end) on the absolute (UTC) timeline.
 *
 * Working with instants instead of wall-clock strings is what keeps the engine
 * DST-safe: local times are converted once, at the edges, by the AvailabilityService.
 */
final readonly class Interval
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        if ($end->lessThan($start)) {
            throw new InvalidArgumentException("Interval end [{$end->toIso8601String()}] is before start [{$start->toIso8601String()}].");
        }
    }

    public static function of(CarbonImmutable $start, int $minutes): self
    {
        return new self($start, $start->addMinutes($minutes));
    }

    public function isEmpty(): bool
    {
        return $this->start->equalTo($this->end);
    }

    public function minutes(): int
    {
        return (int) $this->start->diffInMinutes($this->end, true);
    }

    public function overlaps(self $other): bool
    {
        return $this->start->lessThan($other->end) && $other->start->lessThan($this->end);
    }

    public function contains(self $other): bool
    {
        return $this->start->lessThanOrEqualTo($other->start) && $this->end->greaterThanOrEqualTo($other->end);
    }

    /**
     * @return list<self> zero, one or two pieces
     */
    public function subtract(self $other): array
    {
        if (! $this->overlaps($other)) {
            return [$this];
        }

        $pieces = [];

        if ($this->start->lessThan($other->start)) {
            $pieces[] = new self($this->start, $other->start);
        }

        if ($other->end->lessThan($this->end)) {
            $pieces[] = new self($other->end, $this->end);
        }

        return $pieces;
    }
}
