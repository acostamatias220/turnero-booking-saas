<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00', 'America/Argentina/Buenos_Aires'));
    ['business' => $this->business, 'owner' => $this->owner, 'service' => $this->service] = bookableBusiness(['slug' => 'barberia-el-tano']);
});

function bookingPayload(array $overrides = []): array
{
    return [
        'service_id' => test()->service->id,
        'starts_at' => nextMondayAt('10:00')->toIso8601String(),
        'name' => 'Juana Pérez',
        'email' => 'juana@example.com',
        'phone' => '11 5555-1234',
        ...$overrides,
    ];
}

it('renders the public booking page of a business', function () {
    $this->get('/b/barberia-el-tano')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/booking')
            ->where('business.slug', 'barberia-el-tano')
            ->has('services', 1)
            ->has('staff', 1)
            ->missing('staff.0.email'));
});

it('404s for an unknown business', function () {
    $this->get('/b/no-existe')->assertNotFound();
});

it('books a free turno and redirects to its status page', function () {
    $response = $this->post('/b/barberia-el-tano/turnos', bookingPayload());

    $appointment = Appointment::acrossTenants()->firstOrFail();
    $response->assertRedirect(route('public.appointments.show', ['barberia-el-tano', $appointment->public_id]));
    expect($appointment->status)->toBe(AppointmentStatus::Confirmed);
});

it('sends the customer to the hosted checkout when a deposit is required', function () {
    $this->business->update(['deposit_percent' => 50]);

    $response = $this->post('/b/barberia-el-tano/turnos', bookingPayload(), ['X-Inertia' => 'true']);

    $response->assertStatus(409)->assertHeader('X-Inertia-Location');
    expect($response->headers->get('X-Inertia-Location'))->toContain('/pagosimulado/checkout/cs_');
});

it('shows a friendly error when the slot was just taken', function () {
    $this->post('/b/barberia-el-tano/turnos', bookingPayload());

    $this->from('/b/barberia-el-tano')
        ->post('/b/barberia-el-tano/turnos', bookingPayload(['email' => 'otra@example.com']))
        ->assertRedirect('/b/barberia-el-tano')
        ->assertSessionHas('error');
});

it('validates the booking form', function () {
    $this->post('/b/barberia-el-tano/turnos', bookingPayload(['email' => 'nope', 'name' => '', 'starts_at' => '2020-01-01 10:00']))
        ->assertSessionHasErrors(['email', 'name', 'starts_at']);
});

it('rejects bots that fill the honeypot', function () {
    $this->post('/b/barberia-el-tano/turnos', bookingPayload(['website' => 'http://spam.example']))
        ->assertSessionHasErrors('website');
});

it('rate-limits bookings per customer e-mail', function () {
    foreach (range(9, 13) as $i => $hour) {
        $this->post('/b/barberia-el-tano/turnos', bookingPayload(['starts_at' => nextMondayAt(sprintf('%02d:00', $hour === 13 ? 14 : $hour))->toIso8601String()]));
    }

    $this->post('/b/barberia-el-tano/turnos', bookingPayload(['starts_at' => nextMondayAt('16:00')->toIso8601String()]))
        ->assertTooManyRequests();
});

it('shows the appointment status page and the .ics download', function () {
    $this->post('/b/barberia-el-tano/turnos', bookingPayload());
    $appointment = Appointment::acrossTenants()->firstOrFail();

    $this->get(route('public.appointments.show', ['barberia-el-tano', $appointment->public_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('public/appointment')->where('appointment.status', 'confirmed')->missing('appointment.customer'));

    $this->get(route('public.appointments.calendar', ['barberia-el-tano', $appointment->public_id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
});

it('does not expose appointments through another business slug', function () {
    $this->post('/b/barberia-el-tano/turnos', bookingPayload());
    $appointment = Appointment::acrossTenants()->firstOrFail();
    $other = bookableBusiness(['slug' => 'otra-barberia']);

    $this->get(route('public.appointments.show', ['otra-barberia', $appointment->public_id]))->assertNotFound();
});

describe('customer cancellation via signed link', function () {
    beforeEach(function () {
        $this->post('/b/barberia-el-tano/turnos', bookingPayload());
        $this->appointment = Appointment::acrossTenants()->firstOrFail();
        $this->url = URL::temporarySignedRoute('public.appointments.cancel', now()->addDays(10), ['barberia-el-tano', $this->appointment->public_id]);
    });

    it('requires a valid signature', function () {
        $this->get(route('public.appointments.cancel', ['barberia-el-tano', $this->appointment->public_id]))->assertForbidden();
        $this->post(route('public.appointments.cancel.store', ['barberia-el-tano', $this->appointment->public_id]))->assertForbidden();
    });

    it('shows a confirmation page and cancels on POST', function () {
        $this->get($this->url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('public/cancel')->where('can_cancel', true));

        $this->post($this->url)->assertRedirect(route('public.appointments.show', ['barberia-el-tano', $this->appointment->public_id]));

        expect($this->appointment->fresh()?->status)->toBe(AppointmentStatus::Cancelled)
            ->and($this->appointment->fresh()?->cancellation_reason)->toBe('customer');
    });

    it('refuses to cancel inside the cancellation window', function () {
        $this->travelTo(nextMondayAt('09:00')); // 1h before; policy is 24h

        $this->post($this->url)->assertSessionHas('error');

        expect($this->appointment->fresh()?->status)->toBe(AppointmentStatus::Confirmed);
    });
});
