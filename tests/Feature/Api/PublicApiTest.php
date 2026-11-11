<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00', 'America/Argentina/Buenos_Aires'));
    ['business' => $this->business, 'owner' => $this->owner, 'service' => $this->service] = bookableBusiness(['slug' => 'estudio'], ['duration_minutes' => 60]);
    $this->base = '/api/v1/public/businesses/estudio';
});

it('describes the business with its services and staff', function () {
    $this->getJson($this->base)
        ->assertOk()
        ->assertJsonPath('data.name', $this->business->name)
        ->assertJsonPath('data.services.0.duration_minutes', 60)
        ->assertJsonPath('data.staff.0.name', 'Ana Dueña')
        ->assertJsonMissingPath('data.staff.0.email');
});

it('returns the slots of a day with local times', function () {
    $this->getJson("{$this->base}/availability?service_id={$this->service->id}&date=2026-11-16")
        ->assertOk()
        ->assertJsonPath('data.timezone', 'America/Argentina/Buenos_Aires')
        ->assertJsonPath('data.slots.0.time', '09:00')
        ->assertJsonPath('data.slots.0.starts_at', '2026-11-16T12:00:00Z')
        ->assertJsonPath('data.slots.0.staff_ids', [$this->owner->id]);
});

it('filters availability by professional', function () {
    $bruno = addProfessional($this->business, $this->service);

    $this->getJson("{$this->base}/availability?service_id={$this->service->id}&date=2026-11-16&staff_id={$bruno->id}")
        ->assertOk()
        ->assertJsonPath('data.slots.0.staff_ids', [$bruno->id]);
});

it('lists bookable dates', function () {
    $this->getJson("{$this->base}/available-dates?service_id={$this->service->id}")
        ->assertOk()
        ->assertJsonFragment(['2026-11-16']);
});

it('validates availability queries', function () {
    $this->getJson("{$this->base}/availability?date=mañana")->assertUnprocessable()->assertJsonValidationErrors(['service_id', 'date']);
});

it('books through the API and returns 201', function () {
    $this->postJson("{$this->base}/appointments", [
        'service_id' => $this->service->id,
        'starts_at' => '2026-11-16T12:00:00Z',
        'name' => 'Juana',
        'email' => 'juana@example.com',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.local_start', '09:00')
        ->assertJsonPath('meta.checkout_url', null);
});

it('answers 409 Conflict when the slot is taken', function () {
    $payload = ['service_id' => $this->service->id, 'starts_at' => '2026-11-16T12:00:00Z', 'name' => 'Juana', 'email' => 'juana@example.com'];
    $this->postJson("{$this->base}/appointments", $payload)->assertCreated();

    $this->postJson("{$this->base}/appointments", [...$payload, 'email' => 'otra@example.com'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'slot_unavailable');
});

it('returns the checkout URL when a deposit is needed', function () {
    $this->business->update(['deposit_percent' => 20]);

    $this->postJson("{$this->base}/appointments", [
        'service_id' => $this->service->id, 'starts_at' => '2026-11-16T12:00:00Z', 'name' => 'Juana', 'email' => 'juana@example.com',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.deposit_cents', 300_000);
});
