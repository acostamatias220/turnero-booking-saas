<?php

declare(strict_types=1);

use App\Booking\BookingRequest;
use App\Booking\BookingService;
use App\Booking\SlotUnavailableException;
use App\Enums\AppointmentStatus;
use App\Events\AppointmentBooked;
use App\Models\Appointment;
use App\Models\AppointmentBlock;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00', 'America/Argentina/Buenos_Aires'));
    ['business' => $this->business, 'owner' => $this->owner, 'service' => $this->service] = bookableBusiness(
        [],
        ['duration_minutes' => 45, 'buffer_minutes' => 15, 'price_cents' => 2_000_000],
    );
    $this->actingAsTenant($this->business);
    $this->booking = app(BookingService::class);
});

function bookingFor(int $serviceId, CarbonImmutable $start, ?int $staffId = null, string $email = 'juana@example.com'): BookingRequest
{
    return new BookingRequest($serviceId, $start, $staffId, 'Juana Pérez', $email, '11 5555-1234');
}

it('books a confirmed turno when no deposit is required', function () {
    Event::fake([AppointmentBooked::class]);

    $appointment = $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('10:00')));

    expect($appointment->status)->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->staff_id)->toBe($this->owner->id)
        ->and($appointment->ends_at->diffInMinutes($appointment->starts_at, true))->toEqual(45)
        ->and($appointment->blocked_until->diffInMinutes($appointment->starts_at, true))->toEqual(60)
        ->and($appointment->confirmed_at)->not->toBeNull()
        ->and($appointment->price_cents)->toBe(2_000_000);

    Event::assertDispatched(AppointmentBooked::class);
});

it('writes one 5-minute block per occupied unit, buffer included', function () {
    $appointment = $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('10:00')));

    expect(AppointmentBlock::query()->where('appointment_id', $appointment->id)->count())->toBe(12);
});

it('holds the slot pending a deposit when the business requires one', function () {
    $this->business->update(['deposit_percent' => 30, 'hold_minutes' => 10]);

    $appointment = $this->booking->book($this->business->fresh(), bookingFor($this->service->id, nextMondayAt('10:00')));

    expect($appointment->status)->toBe(AppointmentStatus::PendingPayment)
        ->and($appointment->deposit_cents)->toBe(600_000)
        ->and($appointment->hold_expires_at?->equalTo(CarbonImmutable::now()->addMinutes(10)))->toBeTrue()
        ->and($appointment->confirmed_at)->toBeNull();
});

it('reuses the customer record by e-mail within the business', function () {
    $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('10:00')));
    $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('11:00')));

    expect(Customer::query()->count())->toBe(1);
});

it('refuses a slot that overlaps an existing turno', function () {
    $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('10:00')));

    $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('10:30'), email: 'otro@example.com'));
})->throws(SlotUnavailableException::class);

it('refuses a slot that falls inside the previous buffer', function () {
    $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('10:00')));

    // 10:00 + 45' service = 10:45, but the buffer keeps the professional busy until 11:00.
    $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('10:45'), email: 'otro@example.com'));
})->throws(SlotUnavailableException::class);

it('refuses times outside working hours', function () {
    $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('13:00')));
})->throws(SlotUnavailableException::class);

it('refuses start times that are not aligned to the block grid', function () {
    $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('10:07')));
})->throws(ValidationException::class);

it('refuses a professional who does not perform the service', function () {
    $outsider = App\Models\User::factory()->forBusiness($this->business)->staff()->create();

    $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('10:00'), $outsider->id));
})->throws(ValidationException::class);

it('frees a slot held by an expired deposit so somebody else can book it', function () {
    $this->business->update(['deposit_percent' => 50, 'hold_minutes' => 15]);
    $first = $this->booking->book($this->business->fresh(), bookingFor($this->service->id, nextMondayAt('10:00')));

    $this->travel(16)->minutes();
    $second = $this->booking->book($this->business->fresh(), bookingFor($this->service->id, nextMondayAt('10:00'), email: 'rapida@example.com'));

    expect($first->fresh()?->status)->toBe(AppointmentStatus::Expired)
        ->and($second->status)->toBe(AppointmentStatus::PendingPayment);
});

it('spreads "any professional" bookings to whoever is less busy that day', function () {
    $bruno = addProfessional($this->business, $this->service);
    $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('09:00'), $this->owner->id));

    $any = $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('11:00'), email: 'x@example.com'));

    expect($any->staff_id)->toBe($bruno->id);
});

it('assigns whoever is still free when one professional is taken', function () {
    $bruno = addProfessional($this->business, $this->service);
    $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('10:00'), $bruno->id));

    $any = $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('10:00'), email: 'x@example.com'));

    expect($any->staff_id)->toBe($this->owner->id);
});

it('the database itself rejects overlapping blocks even if the app checks are bypassed', function () {
    $appointment = $this->booking->book($this->business, bookingFor($this->service->id, nextMondayAt('10:00')));

    $rogue = Appointment::factory()->for($this->owner, 'staff')
        ->at(nextMondayAt('10:30'), 30)
        ->create(['service_id' => $this->service->id]);

    expect(fn () => app(App\Booking\SlotBlocks::class)->reserve($rogue))->toThrow(SlotUnavailableException::class)
        ->and(AppointmentBlock::query()->where('appointment_id', $rogue->id)->count())->toBe(0)
        ->and(AppointmentBlock::query()->where('appointment_id', $appointment->id)->count())->toBe(12);
});
