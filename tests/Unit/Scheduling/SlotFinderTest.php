<?php

declare(strict_types=1);

use App\Scheduling\Interval;
use App\Scheduling\IntervalSet;
use App\Scheduling\SlotFinder;
use Carbon\CarbonImmutable;

function at(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2026-03-02 {$time}", 'UTC');
}

function times(array $slots): array
{
    return array_map(fn (CarbonImmutable $s) => $s->format('H:i'), $slots);
}

beforeEach(fn () => $this->finder = new SlotFinder);

it('lays slots on a grid anchored at the start of each working range', function () {
    $working = new IntervalSet([new Interval(at('09:00'), at('10:00')), new Interval(at('14:10'), at('15:00'))]);

    $slots = $this->finder->find($working, new IntervalSet, occupiedMinutes: 30, stepMinutes: 15, notBefore: at('00:00'));

    expect(times($slots))->toBe(['09:00', '09:15', '09:30', '14:10', '14:25']);
});

it('never offers a slot that would run into a break or the end of the day', function () {
    $working = new IntervalSet([new Interval(at('09:00'), at('13:00'))]);

    $slots = $this->finder->find($working, new IntervalSet, occupiedMinutes: 60, stepMinutes: 30, notBefore: at('00:00'));

    expect(end($slots)->format('H:i'))->toBe('12:00');
});

it('skips busy time including the service buffer', function () {
    $working = new IntervalSet([new Interval(at('09:00'), at('11:00'))]);
    $busy = new IntervalSet([new Interval(at('09:30'), at('10:10'))]); // 30' turno + 10' buffer

    $slots = $this->finder->find($working, $busy, occupiedMinutes: 30, stepMinutes: 15, notBefore: at('00:00'));

    expect(times($slots))->toBe(['09:00', '10:15', '10:30']);
});

it('respects the minimum notice', function () {
    $working = new IntervalSet([new Interval(at('09:00'), at('11:00'))]);

    $slots = $this->finder->find($working, new IntervalSet, occupiedMinutes: 30, stepMinutes: 30, notBefore: at('09:40'));

    expect(times($slots))->toBe(['10:00', '10:30']);
});
