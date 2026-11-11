<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Holidays\HolidayProvider;
use App\Models\Appointment;
use App\Models\AppointmentBlock;
use App\Models\Business;
use App\Models\Holiday;
use App\Notifications\AppointmentReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00', 'America/Argentina/Buenos_Aires'));
});

describe('appointments:expire-holds', function () {
    it('expires unpaid holds across every tenant and releases their blocks', function () {
        $a = bookableBusiness();
        $b = bookableBusiness();

        $expiredA = Appointment::factory()->for($a['owner'], 'staff')->pendingPayment(CarbonImmutable::now()->subMinute())->create();
        $expiredB = Appointment::factory()->for($b['owner'], 'staff')->pendingPayment(CarbonImmutable::now()->subMinute())->create();
        $alive = Appointment::factory()->for($a['owner'], 'staff')->pendingPayment(CarbonImmutable::now()->addMinutes(5))
            ->at(CarbonImmutable::now()->addDays(3)->setTime(15, 0))->create();
        AppointmentBlock::query()->insert(['staff_id' => $a['owner']->id, 'starts_at' => $expiredA->starts_at, 'appointment_id' => $expiredA->id]);

        $this->artisan('appointments:expire-holds')->expectsOutputToContain('Released 2')->assertSuccessful();

        expect(Appointment::acrossTenants()->find($expiredA->id)?->status)->toBe(AppointmentStatus::Expired)
            ->and(Appointment::acrossTenants()->find($expiredB->id)?->status)->toBe(AppointmentStatus::Expired)
            ->and(Appointment::acrossTenants()->find($alive->id)?->status)->toBe(AppointmentStatus::PendingPayment)
            ->and(AppointmentBlock::query()->count())->toBe(0);
    });
});

describe('appointments:send-reminders', function () {
    it('reminds confirmed turnos within the next 24h exactly once', function () {
        ['owner' => $owner] = bookableBusiness();
        $soon = Appointment::factory()->for($owner, 'staff')->at(CarbonImmutable::now()->addHours(20))->create();
        Appointment::factory()->for($owner, 'staff')->at(CarbonImmutable::now()->addHours(30))->create();
        Appointment::factory()->for($owner, 'staff')->at(CarbonImmutable::now()->addHours(5))->status(AppointmentStatus::Cancelled)->create();

        $this->artisan('appointments:send-reminders')->expectsOutputToContain('Queued 1')->assertSuccessful();
        $this->artisan('appointments:send-reminders')->expectsOutputToContain('Queued 0')->assertSuccessful();

        Notification::assertSentTimes(AppointmentReminderNotification::class, 1);
        expect(Appointment::acrossTenants()->find($soon->id)?->reminder_sent_at)->not->toBeNull();
    });

    it('renders the reminder with a signed cancellation link', function () {
        ['business' => $business, 'owner' => $owner] = bookableBusiness(['cancellation_notice_hours' => 2]);
        $appointment = Appointment::factory()->for($owner, 'staff')->at(CarbonImmutable::now()->addHours(20))->create();
        $this->actingAsTenant($business);

        $mail = (new AppointmentReminderNotification($appointment->fresh()))->toMail($appointment->customer);

        expect($mail->subject)->toContain($business->name)
            ->and(implode("\n", $mail->introLines))->toContain('signature=');
    });
});

describe('holidays:import', function () {
    it('imports Argentine holidays for every business and is idempotent', function () {
        Http::fake(['api.argentinadatos.com/*' => Http::response([
            ['fecha' => '2026-01-01', 'tipo' => 'inamovible', 'nombre' => 'Año nuevo'],
            ['fecha' => '2026-03-24', 'tipo' => 'inamovible', 'nombre' => 'Día Nacional de la Memoria por la Verdad y la Justicia'],
            ['fecha' => 'not-a-date', 'tipo' => 'x', 'nombre' => 'ignored'],
        ])]);
        Business::factory()->count(2)->create();

        $this->artisan('holidays:import', ['year' => 2026])->assertSuccessful();
        $this->artisan('holidays:import', ['year' => 2026])->assertSuccessful();

        expect(Holiday::acrossTenants()->count())->toBe(4);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/feriados/2026'));
    });

    it('can target a single business', function () {
        $this->app->instance(HolidayProvider::class, new class implements HolidayProvider
        {
            public function forYear(int $year): array
            {
                return [['date' => "{$year}-07-09", 'name' => 'Día de la Independencia']];
            }
        });
        $target = Business::factory()->create();
        Business::factory()->create();

        $this->artisan('holidays:import', ['year' => 2027, '--business' => $target->slug])->assertSuccessful();

        expect(Holiday::acrossTenants()->pluck('business_id')->all())->toBe([$target->id]);
    });

    it('fails gracefully when the API is down', function () {
        Http::fake(['*' => Http::response('oops', 503)]);

        $this->artisan('holidays:import', ['year' => 2026])->assertFailed();
    });
});
