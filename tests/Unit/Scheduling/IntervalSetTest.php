<?php

declare(strict_types=1);

use App\Scheduling\Interval;
use App\Scheduling\IntervalSet;
use Carbon\CarbonImmutable;

function span(string $from, string $to): Interval
{
    return new Interval(CarbonImmutable::parse("2026-03-02 {$from}", 'UTC'), CarbonImmutable::parse("2026-03-02 {$to}", 'UTC'));
}

function describeSet(IntervalSet $set): array
{
    return array_map(fn (Interval $i) => $i->start->format('H:i').'-'.$i->end->format('H:i'), $set->intervals);
}

it('sorts and merges overlapping or touching intervals', function () {
    $set = new IntervalSet([span('14:00', '15:00'), span('09:00', '10:00'), span('09:30', '11:00'), span('11:00', '12:00')]);

    expect(describeSet($set))->toBe(['09:00-12:00', '14:00-15:00']);
});

it('subtracts busy time from working time', function () {
    $working = new IntervalSet([span('09:00', '13:00'), span('14:00', '18:00')]);
    $busy = new IntervalSet([span('10:00', '10:45'), span('12:30', '14:30'), span('17:00', '19:00')]);

    expect(describeSet($working->subtract($busy)))->toBe(['09:00-10:00', '10:45-12:30', '14:30-17:00'])
        ->and($working->subtract($busy)->totalMinutes())->toBe(60 + 105 + 150);
});

it('drops empty intervals', function () {
    expect((new IntervalSet([span('09:00', '09:00')]))->isEmpty())->toBeTrue();
});
