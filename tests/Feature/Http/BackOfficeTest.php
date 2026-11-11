<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Holiday;
use App\Models\Service;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\WorkingHour;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00', 'America/Argentina/Buenos_Aires'));
    ['business' => $this->business, 'owner' => $this->owner, 'service' => $this->service] = bookableBusiness();
    $this->staff = addProfessional($this->business, $this->service);
});

/** Run assertions that query tenant models outside of a request. */
function inTenant(callable $callback): mixed
{
    return app(TenantContext::class)->run(test()->business, $callback);
}

describe('agenda', function () {
    it('shows one column per professional with positioned turnos', function () {
        Appointment::factory()->for($this->staff, 'staff')
            ->at(CarbonImmutable::parse('2026-11-16 10:30', 'America/Argentina/Buenos_Aires')->utc(), 45)
            ->create(['service_id' => $this->service->id]);

        $this->actingAs($this->owner)->get('/agenda?date=2026-11-16')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('agenda')
                ->has('staff', 2)
                ->has('appointments', 1)
                ->where('appointments.0.start_minute', 630)
                ->where('appointments.0.end_minute', 675)
                ->where('date_label', 'Lunes 16 de noviembre'));
    });

    it('shows professionals only their own column', function () {
        $this->actingAs($this->staff)->get('/agenda')
            ->assertInertia(fn (Assert $page) => $page->has('staff', 1)->where('staff.0.id', $this->staff->id)->where('is_today', true));
    });
});

describe('appointments list', function () {
    it('paginates and filters', function () {
        Appointment::factory()->for($this->owner, 'staff')->create(['service_id' => $this->service->id]);
        Appointment::factory()->for($this->owner, 'staff')->at(CarbonImmutable::now()->addDays(4)->setTime(15, 0))->status(AppointmentStatus::Cancelled)->create(['service_id' => $this->service->id]);

        $this->actingAs($this->owner)->get('/turnos?status=cancelled')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('appointments/index')->has('appointments.data', 1)->where('filters.status', 'cancelled'));
    });

    it('lets staff cancel one of their turnos and notifies the customer', function () {
        $appointment = Appointment::factory()->for($this->staff, 'staff')->create(['service_id' => $this->service->id]);

        $this->actingAs($this->staff)->from('/turnos')
            ->patch(route('appointments.status', $appointment->public_id), ['status' => 'cancelled'])
            ->assertRedirect('/turnos')
            ->assertSessionHas('success');

        expect(inTenant(fn () => $appointment->fresh()?->status))->toBe(AppointmentStatus::Cancelled);
        Notification::assertSentTo(inTenant(fn () => $appointment->customer), App\Notifications\AppointmentCancelledNotification::class);
    });

    it('forbids staff from touching a colleague turno', function () {
        $appointment = Appointment::factory()->for($this->owner, 'staff')->create(['service_id' => $this->service->id]);

        $this->actingAs($this->staff)->patch(route('appointments.status', $appointment->public_id), ['status' => 'cancelled'])->assertForbidden();
    });

    it('explains invalid transitions', function () {
        $appointment = Appointment::factory()->for($this->owner, 'staff')->create(['service_id' => $this->service->id]);

        $this->actingAs($this->owner)->patch(route('appointments.status', $appointment->public_id), ['status' => 'completed'])
            ->assertSessionHas('error');
    });
});

describe('services', function () {
    it('lists services', function () {
        $this->actingAs($this->staff)->get('/servicios')->assertOk()->assertInertia(fn (Assert $page) => $page->component('services/index')->has('services', 1));
    });

    it('owners create services in pesos and assign professionals', function () {
        $this->actingAs($this->owner)->post('/servicios', [
            'name' => 'Barba', 'duration_minutes' => 20, 'buffer_minutes' => 5, 'price' => '8500.50',
            'is_active' => true, 'staff_ids' => [$this->staff->id],
        ])->assertSessionHasNoErrors();

        $service = inTenant(fn () => Service::query()->where('name', 'Barba')->with('staff')->firstOrFail());
        expect($service->price_cents)->toBe(850_050)
            ->and($service->staff->pluck('id')->all())->toBe([$this->staff->id]);
    });

    it('rejects durations that break the 5-minute grid', function () {
        $this->actingAs($this->owner)->post('/servicios', ['name' => 'Raro', 'duration_minutes' => 22, 'buffer_minutes' => 3, 'price' => 1])
            ->assertSessionHasErrors(['duration_minutes', 'buffer_minutes']);
    });

    it('staff cannot manage services', function () {
        $this->actingAs($this->staff)->post('/servicios', ['name' => 'X', 'duration_minutes' => 30, 'buffer_minutes' => 0, 'price' => 1])->assertForbidden();
    });

    it('updates a service', function () {
        $this->actingAs($this->owner)->put("/servicios/{$this->service->id}", [
            'name' => 'Corte clásico', 'duration_minutes' => 40, 'buffer_minutes' => 0, 'price' => 12000, 'is_active' => true, 'staff_ids' => [],
        ])->assertSessionHasNoErrors();

        expect($this->service->fresh()?->name)->toBe('Corte clásico');
    });

    it('deactivates instead of deleting a service with history', function () {
        Appointment::factory()->for($this->owner, 'staff')->create(['service_id' => $this->service->id]);

        $this->actingAs($this->owner)->delete("/servicios/{$this->service->id}")->assertSessionHas('success');

        expect($this->service->fresh()?->is_active)->toBeFalse();
    });

    it('deletes an unused service', function () {
        $unused = Service::factory()->create(['business_id' => $this->business->id]);

        $this->actingAs($this->owner)->delete("/servicios/{$unused->id}");

        expect(Service::acrossTenants()->find($unused->id))->toBeNull();
    });
});

describe('team', function () {
    it('is owner-only', function () {
        $this->actingAs($this->staff)->get('/equipo')->assertForbidden();
        $this->actingAs($this->owner)->get('/equipo')->assertOk()->assertInertia(fn (Assert $page) => $page->component('team/index')->has('members', 2));
    });

    it('adds a professional with services and a split schedule', function () {
        $this->actingAs($this->owner)->post('/equipo', [
            'name' => 'Carla', 'title' => 'Colorista', 'email' => 'carla@example.com', 'password' => 'password123',
            'is_bookable' => true, 'service_ids' => [$this->service->id],
            'working_hours' => [
                ['weekday' => 2, 'starts_at' => '10:00', 'ends_at' => '14:00'],
                ['weekday' => 2, 'starts_at' => '15:00', 'ends_at' => '19:00'],
            ],
        ])->assertSessionHasNoErrors();

        $carla = User::query()->where('email', 'carla@example.com')->firstOrFail();
        expect($carla->business_id)->toBe($this->business->id)
            ->and($carla->isOwner())->toBeFalse()
            ->and(inTenant(fn () => WorkingHour::query()->where('user_id', $carla->id)->count()))->toBe(2);
    });

    it('rejects overlapping ranges on the same day', function () {
        $this->actingAs($this->owner)->put("/equipo/{$this->staff->id}", [
            'name' => 'Bruno', 'email' => $this->staff->email,
            'working_hours' => [
                ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '13:00'],
                ['weekday' => 1, 'starts_at' => '12:00', 'ends_at' => '18:00'],
            ],
        ])->assertSessionHasErrors('working_hours');
    });

    it('replaces the schedule on update', function () {
        $this->actingAs($this->owner)->put("/equipo/{$this->staff->id}", [
            'name' => 'Bruno', 'email' => $this->staff->email, 'is_bookable' => true,
            'working_hours' => [['weekday' => 6, 'starts_at' => '09:00', 'ends_at' => '13:00']],
        ])->assertSessionHasNoErrors();

        expect(inTenant(fn () => WorkingHour::query()->where('user_id', $this->staff->id)->pluck('weekday')->all()))->toBe([6]);
    });

    it('protects owners and professionals with upcoming turnos from deletion', function () {
        Appointment::factory()->for($this->staff, 'staff')->create(['service_id' => $this->service->id]);

        $this->actingAs($this->owner)->delete("/equipo/{$this->owner->id}")->assertSessionHas('error');
        $this->actingAs($this->owner)->delete("/equipo/{$this->staff->id}")->assertSessionHas('error');

        expect(User::query()->count())->toBe(2);
    });

    it('deletes a professional without upcoming turnos', function () {
        $this->actingAs($this->owner)->delete("/equipo/{$this->staff->id}")->assertSessionHas('success');

        expect(User::query()->find($this->staff->id))->toBeNull();
    });
});

describe('time off and holidays', function () {
    it('stores time off in local time as UTC instants', function () {
        $this->actingAs($this->owner)->post('/ausencias', [
            'user_id' => $this->staff->id, 'starts_at' => '2026-11-20T09:00', 'ends_at' => '2026-11-20T12:00', 'reason' => 'Dentista',
        ])->assertSessionHasNoErrors();

        $timeOff = inTenant(fn () => TimeOff::query()->firstOrFail());
        expect($timeOff->starts_at->format('Y-m-d H:i'))->toBe('2026-11-20 12:00');

        $this->actingAs($this->owner)->get('/ausencias')->assertInertia(fn (Assert $page) => $page
            ->component('time-off/index')
            ->where('time_off.0.starts_at', '2026-11-20 09:00')
            ->where('time_off.0.who', 'Bruno Profesional'));
    });

    it('adds and removes closed days', function () {
        $this->actingAs($this->owner)->post('/feriados', ['date' => '2026-12-24', 'name' => 'Nochebuena'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post('/feriados', ['date' => '2026-12-24', 'name' => 'Duplicado'])->assertSessionHasErrors('date');

        $holiday = inTenant(fn () => Holiday::query()->firstOrFail());
        $this->actingAs($this->owner)->delete("/feriados/{$holiday->id}");

        expect(Holiday::acrossTenants()->count())->toBe(0);
    });

    it('deletes time off', function () {
        $timeOff = TimeOff::factory()->create(['business_id' => $this->business->id]);

        $this->actingAs($this->owner)->delete("/ausencias/{$timeOff->id}")->assertSessionHas('success');

        expect(TimeOff::acrossTenants()->count())->toBe(0);
    });
});

describe('business settings', function () {
    it('renders and updates the booking rules', function () {
        $this->actingAs($this->owner)->get('/settings/business')->assertOk()->assertInertia(fn (Assert $page) => $page->component('settings/business'));

        $this->actingAs($this->owner)->patch('/settings/business', [
            ...$this->business->only(['name', 'category', 'timezone', 'accent_color']),
            'slug' => 'nuevo-slug', 'slot_interval_minutes' => 30, 'min_notice_minutes' => 120, 'booking_horizon_days' => 60,
            'cancellation_notice_hours' => 12, 'deposit_percent' => 25, 'hold_minutes' => 10,
        ])->assertSessionHasNoErrors();

        expect($this->business->fresh()?->deposit_percent)->toBe(25)
            ->and($this->business->fresh()?->slug)->toBe('nuevo-slug');
    });

    it('validates slugs and colours', function () {
        $this->actingAs($this->owner)->patch('/settings/business', ['slug' => 'Con Espacios', 'accent_color' => 'red'])
            ->assertSessionHasErrors(['slug', 'accent_color']);
    });

    it('is owner-only', function () {
        $this->actingAs($this->staff)->get('/settings/business')->assertForbidden();
    });
});
