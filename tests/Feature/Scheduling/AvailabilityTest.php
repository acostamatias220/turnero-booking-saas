<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Holiday;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\WorkingHour;
use App\Scheduling\AvailabilityService;
use App\Scheduling\Slot;
use Carbon\CarbonImmutable;

const BA = 'America/Argentina/Buenos_Aires';

beforeEach(function () {
    // Wednesday 11 Nov 2026, 10:00 in Buenos Aires. "Next Monday" is 16 Nov.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00', BA));

    ['business' => $this->business, 'owner' => $this->owner, 'service' => $this->service] = bookableBusiness(
        ['slot_interval_minutes' => 30],
        ['duration_minutes' => 30],
    );
    $this->actingAsTenant($this->business);
    $this->engine = app(AvailabilityService::class);
    $this->monday = CarbonImmutable::parse('2026-11-16', BA);
});

/** @param list<Slot> $slots */
function localTimes(array $slots): array
{
    return array_map(fn (Slot $s) => $s->startsAt->setTimezone(BA)->format('H:i'), $slots);
}

it('offers slots within working hours and skips the lunch break', function () {
    $times = localTimes($this->engine->slotsFor($this->business, $this->service, $this->monday));

    expect($times)->toHaveCount(16)
        ->and($times[0])->toBe('09:00')
        ->and($times)->toContain('12:30', '14:00', '17:30')
        ->not->toContain('13:00', '13:30', '18:00');
});

it('returns instants in UTC (Buenos Aires is UTC-3)', function () {
    $first = $this->engine->slotsFor($this->business, $this->service, $this->monday)[0];

    expect($first->startsAt->utc()->format('H:i'))->toBe('12:00')
        ->and($first->jsonSerialize()['time'])->toBe('09:00');
});

it('has nothing on days off (weekend)', function () {
    expect($this->engine->slotsFor($this->business, $this->service, CarbonImmutable::parse('2026-11-15', BA)))->toBe([]);
});

it('blocks existing turnos plus the service buffer', function () {
    $this->service->update(['buffer_minutes' => 15]);
    Appointment::factory()->for($this->owner, 'staff')
        ->at(CarbonImmutable::parse('2026-11-16 10:00', BA)->utc(), 30, 15)
        ->create(['service_id' => $this->service->id, 'business_id' => $this->business->id]);

    $times = localTimes($this->engine->slotsFor($this->business, $this->service->fresh(), $this->monday));

    // A new 30'+15' turno can't start at 09:30 (would overlap 10:00) nor 10:00/10:30 (busy until 10:45).
    expect($times)->toContain('09:00')->not->toContain('09:30', '10:00', '10:30')->toContain('11:00');
});

it('ignores cancelled turnos and expired deposit holds', function () {
    $start = CarbonImmutable::parse('2026-11-16 09:00', BA)->utc();
    Appointment::factory()->for($this->owner, 'staff')->at($start)->status(AppointmentStatus::Cancelled)->create(['service_id' => $this->service->id]);
    Appointment::factory()->for($this->owner, 'staff')->at($start->addMinutes(30))->pendingPayment(CarbonImmutable::now()->subMinute())->create(['service_id' => $this->service->id]);

    expect(localTimes($this->engine->slotsFor($this->business, $this->service, $this->monday)))->toContain('09:00', '09:30');
});

it('a live deposit hold does block the slot', function () {
    Appointment::factory()->for($this->owner, 'staff')->at(CarbonImmutable::parse('2026-11-16 09:00', BA)->utc())->pendingPayment()->create(['service_id' => $this->service->id]);

    expect(localTimes($this->engine->slotsFor($this->business, $this->service, $this->monday)))->not->toContain('09:00');
});

it('removes personal and business-wide time off', function () {
    TimeOff::factory()->create([
        'business_id' => $this->business->id,
        'user_id' => $this->owner->id,
        'starts_at' => CarbonImmutable::parse('2026-11-16 15:00', BA)->utc(),
        'ends_at' => CarbonImmutable::parse('2026-11-16 18:00', BA)->utc(),
    ]);
    TimeOff::factory()->create([
        'business_id' => $this->business->id,
        'user_id' => null,
        'starts_at' => CarbonImmutable::parse('2026-11-16 09:00', BA)->utc(),
        'ends_at' => CarbonImmutable::parse('2026-11-16 10:00', BA)->utc(),
    ]);

    $times = localTimes($this->engine->slotsFor($this->business, $this->service, $this->monday));

    expect($times)->toBe(['10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '14:00', '14:30']);
});

it('is closed on holidays', function () {
    Holiday::query()->create(['date' => '2026-11-16', 'name' => 'Día de la Soberanía Nacional']);

    expect($this->engine->slotsFor($this->business, $this->service, $this->monday))->toBe([]);
});

it('respects the minimum notice and never offers the past', function () {
    $this->business->update(['min_notice_minutes' => 120]);
    WorkingHour::factory()->create(['user_id' => $this->owner->id, 'weekday' => 3, 'starts_at' => '08:00', 'ends_at' => '13:00']);

    $times = localTimes($this->engine->slotsFor($this->business->fresh(), $this->service, CarbonImmutable::parse('2026-11-11', BA)));

    expect($times[0])->toBe('12:00'); // now is 10:00, +2h notice
});

it('does not offer days beyond the booking horizon', function () {
    $this->business->update(['booking_horizon_days' => 3]);

    expect($this->engine->slotsFor($this->business->fresh(), $this->service, $this->monday))->toBe([]);
});

it('merges professionals: each slot lists who is free', function () {
    $bruno = addProfessional($this->business, $this->service);
    Appointment::factory()->for($this->owner, 'staff')->at(CarbonImmutable::parse('2026-11-16 09:00', BA)->utc())->create(['service_id' => $this->service->id]);

    $slots = $this->engine->slotsFor($this->business, $this->service, $this->monday);

    expect($slots[0]->staffIds)->toBe([$bruno->id])
        ->and($slots[1]->staffIds)->toEqualCanonicalizing([$this->owner->id, $bruno->id]);
});

it('only considers professionals who perform the service', function () {
    $other = User::factory()->forBusiness($this->business)->staff()->create();
    WorkingHour::factory()->create(['user_id' => $other->id, 'weekday' => 1, 'starts_at' => '06:00', 'ends_at' => '08:00']);

    expect(localTimes($this->engine->slotsFor($this->business, $this->service, $this->monday))[0])->toBe('09:00');
});

it('lists the dates that have availability for the date picker', function () {
    $dates = $this->engine->availableDates($this->business, $this->service);

    expect($dates)->toContain('2026-11-11', '2026-11-16')->not->toContain('2026-11-14', '2026-11-15');
});

describe('daylight saving time', function () {
    it('keeps wall-clock hours on the day clocks go back (America/Santiago)', function () {
        // Chile leaves DST at Saturday 4 April 2026 24:00 → 23:00 (UTC-3 → UTC-4).
        $this->travelTo(CarbonImmutable::parse('2026-04-01 08:00', 'America/Santiago'));
        $business = Business::factory()->create(['timezone' => 'America/Santiago', 'slot_interval_minutes' => 60]);
        $owner = User::factory()->forBusiness($business)->create();
        $this->actingAsTenant($business);
        WorkingHour::factory()->create(['user_id' => $owner->id, 'weekday' => 6, 'starts_at' => '09:00', 'ends_at' => '12:00']);
        WorkingHour::factory()->create(['user_id' => $owner->id, 'weekday' => 7, 'starts_at' => '09:00', 'ends_at' => '12:00']);
        $service = App\Models\Service::factory()->create(['business_id' => $business->id, 'duration_minutes' => 60]);
        $service->staff()->attach($owner);

        $saturday = $this->engine->slotsFor($business, $service, CarbonImmutable::parse('2026-04-04', 'America/Santiago'));
        $sunday = $this->engine->slotsFor($business, $service, CarbonImmutable::parse('2026-04-05', 'America/Santiago'));

        $local = fn (array $slots) => array_map(fn (Slot $s) => $s->jsonSerialize()['time'], $slots);
        $utc = fn (array $slots) => array_map(fn (Slot $s) => $s->startsAt->utc()->format('H:i'), $slots);

        // Same local times both days…
        expect($local($saturday))->toBe(['09:00', '10:00', '11:00'])
            ->and($local($sunday))->toBe(['09:00', '10:00', '11:00'])
            // …but the UTC instants shift by one hour (UTC-3 → UTC-4).
            ->and($utc($saturday))->toBe(['12:00', '13:00', '14:00'])
            ->and($utc($sunday))->toBe(['13:00', '14:00', '15:00']);
    });

    it('does not invent slots in the hour that does not exist when clocks go forward', function () {
        // Chile enters DST at Saturday 5 Sep 2026 24:00 → Sunday 01:00: local 00:00–00:59 never exists,
        // so a 00:00–03:00 shift is only 2 real hours long.
        $this->travelTo(CarbonImmutable::parse('2026-09-01 08:00', 'America/Santiago'));
        $business = Business::factory()->create(['timezone' => 'America/Santiago', 'slot_interval_minutes' => 60]);
        $owner = User::factory()->forBusiness($business)->create();
        $this->actingAsTenant($business);
        WorkingHour::factory()->create(['user_id' => $owner->id, 'weekday' => 7, 'starts_at' => '00:00', 'ends_at' => '03:00']);
        $service = App\Models\Service::factory()->create(['business_id' => $business->id, 'duration_minutes' => 60]);
        $service->staff()->attach($owner);

        $slots = $this->engine->slotsFor($business, $service, CarbonImmutable::parse('2026-09-06', 'America/Santiago'));

        expect($slots)->toHaveCount(2);
    });
});
