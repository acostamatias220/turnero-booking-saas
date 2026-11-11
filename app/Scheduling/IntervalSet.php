<?php

declare(strict_types=1);

namespace App\Scheduling;

/**
 * An ordered, non-overlapping set of intervals with set algebra.
 */
final readonly class IntervalSet
{
    /** @var list<Interval> */
    public array $intervals;

    /**
     * @param  iterable<Interval>  $intervals
     */
    public function __construct(iterable $intervals = [])
    {
        $this->intervals = self::normalize([...$intervals]);
    }

    public function isEmpty(): bool
    {
        return $this->intervals === [];
    }

    public function subtract(self $busy): self
    {
        $result = $this->intervals;

        foreach ($busy->intervals as $cut) {
            $next = [];

            foreach ($result as $interval) {
                array_push($next, ...$interval->subtract($cut));
            }

            $result = $next;
        }

        return new self($result);
    }

    public function contains(Interval $candidate): bool
    {
        foreach ($this->intervals as $interval) {
            if ($interval->contains($candidate)) {
                return true;
            }
        }

        return false;
    }

    public function totalMinutes(): int
    {
        return array_sum(array_map(fn (Interval $i): int => $i->minutes(), $this->intervals));
    }

    /**
     * Sort and merge touching/overlapping intervals; drop empty ones.
     *
     * @param  list<Interval>  $intervals
     * @return list<Interval>
     */
    private static function normalize(array $intervals): array
    {
        $intervals = array_values(array_filter($intervals, fn (Interval $i): bool => ! $i->isEmpty()));
        usort($intervals, fn (Interval $a, Interval $b): int => $a->start <=> $b->start);

        $merged = [];

        foreach ($intervals as $interval) {
            $last = $merged === [] ? null : $merged[array_key_last($merged)];

            if ($last !== null && $interval->start->lessThanOrEqualTo($last->end)) {
                $merged[array_key_last($merged)] = new Interval(
                    $last->start,
                    $interval->end->greaterThan($last->end) ? $interval->end : $last->end,
                );

                continue;
            }

            $merged[] = $interval;
        }

        return $merged;
    }
}
