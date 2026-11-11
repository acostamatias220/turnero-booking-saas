<?php

declare(strict_types=1);

use App\Booking\BookingRequest;
use App\Booking\BookingService;
use App\Models\Appointment;
use App\Notifications\AppointmentCancelledNotification;
use App\Notifications\AppointmentConfirmedNotification;
use App\Support\IcsCalendar;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00', 'America/Argentina/Buenos_Aires'));
    ['business' => $this->business, 'owner' => $this->owner, 'service' => $this->service] = bookableBusiness(['address' => 'Av. Corrientes 1234, CABA']);
    $this->actingAsTenant($this->business);
});

it('e-mails a confirmation with an .ics attachment and a signed cancel link', function () {
    Notification::fake();

    $appointment = app(BookingService::class)->book($this->business, new BookingRequest($this->service->id, nextMondayAt('10:00'), null, 'Juana', 'juana@example.com'));

    Notification::assertSentTo($appointment->customer, AppointmentConfirmedNotification::class, function ($notification) use ($appointment) {
        $mail = $notification->toMail($appointment->customer);

        return $mail->subject === "Turno confirmado · {$this->business->name}"
            && str_contains(implode("\n", $mail->outroLines), 'signature=')
            && $mail->rawAttachments[0]['name'] === 'turno.ics';
    });
});

it('queues notifications and carries the tenant to the worker', function () {
    config(['queue.default' => 'database']);
    $appointment = app(BookingService::class)->book($this->business, new BookingRequest($this->service->id, nextMondayAt('10:00'), null, 'Juana', 'juana@example.com'));

    $payload = json_decode((string) Illuminate\Support\Facades\DB::table('jobs')->value('payload'), true);
    expect($payload['tenant_id'])->toBe($this->business->id);

    // Simulate a fresh worker: no tenant in memory, it must come from the payload.
    $this->forgetTenant();
    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->assertSuccessful();

    expect(Illuminate\Support\Facades\DB::table('jobs')->count())->toBe(0)
        ->and(Illuminate\Support\Facades\DB::table('failed_jobs')->count())->toBe(0)
        ->and(app(TenantContext::class)->check())->toBeFalse();
    unset($appointment);
});

it('builds a valid iCalendar event in UTC', function () {
    $appointment = Appointment::factory()->for($this->owner, 'staff')->at(nextMondayAt('10:00'))->create(['service_id' => $this->service->id]);

    $ics = IcsCalendar::forAppointment($appointment->load(['business', 'service', 'staff']));

    expect($ics)->toStartWith("BEGIN:VCALENDAR\r\n")
        ->toContain('DTSTART:20261116T130000Z')
        ->toContain('LOCATION:Av. Corrientes 1234\, CABA')
        ->toContain("UID:{$appointment->public_id}@turnero")
        ->toEndWith("END:VCALENDAR\r\n");

    foreach (explode("\r\n", $ics) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }
});

it('mentions the refund in the cancellation e-mail when a deposit was paid', function () {
    $appointment = Appointment::factory()->for($this->owner, 'staff')->at(nextMondayAt('10:00'))->create(['service_id' => $this->service->id, 'deposit_cents' => 100_000]);
    App\Models\Payment::factory()->create(['appointment_id' => $appointment->id, 'status' => 'refund_required']);

    $mail = (new AppointmentCancelledNotification($appointment->fresh()))->toMail($appointment->customer);

    expect(implode("\n", $mail->introLines))->toContain('devolverte la seña');
});
