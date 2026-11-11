<?php

declare(strict_types=1);

namespace App\Scheduling;

use Carbon\CarbonImmutable;

/**
 * Pure slot generation, no I/O: given when someone works and when they are busy,
 * return every start time where `$occupiedMinutes` fit.
 *
 * Candidate starts are laid on a grid anchored at the start of each working range
 * (09:00, 09:15, 09:30…), which is what customers expect to see on a booking page.
 */
final class SlotFinder
{
    /**
     * @return list<CarbonImmutable> UTC start instants
     */
    public function find(
        IntervalSet $working,
        IntervalSet $busy,
        int $occupiedMinutes,
        int $stepMinutes,
        CarbonImmutable $notBefore,
    ): array {
        $free = $working->subtract($busy);
        $slots = [];

        foreach ($working->intervals as $range) {
            for ($start = $range->start; $start->addMinutes($occupiedMinutes)->lessThanOrEqualTo($range->end); $start = $start->addMinutes($stepMinutes)) {
                if ($start->lessThan($notBefore)) {
                    continue;
                }

                if ($free->contains(Interval::of($start, $occupiedMinutes))) {
                    $slots[] = $start;
                }
            }
        }

        return $slots;
    }
}
