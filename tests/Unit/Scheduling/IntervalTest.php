<?php

declare(strict_types=1);

use App\Scheduling\Interval;
use Carbon\CarbonImmutable;

function iv(string $from, string $to): Interval
{
    return new Interval(CarbonImmutable::parse("2026-03-02 {$from}", 'UTC'), CarbonImmutable::parse("2026-03-02 {$to}", 'UTC'));
}

it('rejects an end before the start', function () {
    iv('10:00', '09:00');
})->throws(InvalidArgumentException::class);

it('treats intervals as half-open, so back-to-back turnos do not overlap', function () {
    expect(iv('09:00', '09:30')->overlaps(iv('09:30', '10:00')))->toBeFalse()
        ->and(iv('09:00', '09:31')->overlaps(iv('09:30', '10:00')))->toBeTrue();
});

it('subtracts into zero, one or two pieces', function (Interval $base, Interval $cut, array $expected) {
    $pieces = array_map(fn (Interval $i) => $i->start->format('H:i').'-'.$i->end->format('H:i'), $base->subtract($cut));

    expect($pieces)->toBe($expected);
})->with([
    'no overlap' => [iv('09:00', '10:00'), iv('11:00', '12:00'), ['09:00-10:00']],
    'covers everything' => [iv('09:00', '10:00'), iv('08:00', '11:00'), []],
    'cuts the start' => [iv('09:00', '12:00'), iv('08:00', '10:00'), ['10:00-12:00']],
    'cuts the end' => [iv('09:00', '12:00'), iv('11:00', '13:00'), ['09:00-11:00']],
    'splits in two' => [iv('09:00', '18:00'), iv('13:00', '14:00'), ['09:00-13:00', '14:00-18:00']],
]);

it('knows its length and containment', function () {
    expect(iv('09:00', '10:30')->minutes())->toBe(90)
        ->and(iv('09:00', '18:00')->contains(iv('13:00', '14:00')))->toBeTrue()
        ->and(iv('09:00', '12:00')->contains(iv('11:30', '12:30')))->toBeFalse();
});
