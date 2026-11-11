<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    ['business' => $this->business, 'owner' => $this->owner, 'service' => $this->service] = bookableBusiness();
    $this->staff = addProfessional($this->business, $this->service);
});

it('issues a Sanctum token for valid credentials', function () {
    $this->postJson('/api/v1/auth/token', ['email' => $this->owner->email, 'password' => 'password', 'device_name' => 'iPhone'])
        ->assertCreated()
        ->assertJsonStructure(['token', 'token_type', 'expires_at', 'abilities']);
});

it('rejects bad credentials with a generic message', function () {
    $this->postJson('/api/v1/auth/token', ['email' => $this->owner->email, 'password' => 'nope', 'device_name' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('throttles token brute force', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/token', ['email' => $this->owner->email, 'password' => 'nope', 'device_name' => 'x']);
    }

    $this->postJson('/api/v1/auth/token', ['email' => $this->owner->email, 'password' => 'nope', 'device_name' => 'x'])->assertTooManyRequests();
});

it('requires authentication', function () {
    $this->getJson('/api/v1/appointments')->assertUnauthorized();
});

it('returns the current member', function () {
    $this->withToken($this->owner->createToken('t')->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.role', 'owner')
        ->assertJsonPath('data.business.slug', $this->business->slug);
});

it('owners list every appointment; professionals only theirs', function () {
    Appointment::factory()->for($this->owner, 'staff')->create(['service_id' => $this->service->id]);
    Appointment::factory()->for($this->staff, 'staff')->at(CarbonImmutable::now()->addDays(3)->setTime(14, 0))->create(['service_id' => $this->service->id]);

    $this->withToken($this->owner->createToken('t', ['appointments:read'])->plainTextToken)
        ->getJson('/api/v1/appointments')->assertOk()->assertJsonCount(2, 'data');

    app('auth')->forgetGuards();

    $this->withToken($this->staff->createToken('t', ['appointments:read'])->plainTextToken)
        ->getJson('/api/v1/appointments')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.staff.id', $this->staff->id);
});

it('filters by status and date range in the business timezone', function () {
    Appointment::factory()->for($this->owner, 'staff')->at(CarbonImmutable::parse('2026-12-01 23:30', 'America/Argentina/Buenos_Aires')->utc())->create(['service_id' => $this->service->id]);
    Appointment::factory()->for($this->owner, 'staff')->at(CarbonImmutable::parse('2026-12-02 10:00', 'America/Argentina/Buenos_Aires')->utc())->status(AppointmentStatus::Cancelled)->create(['service_id' => $this->service->id]);

    $token = $this->owner->createToken('t', ['appointments:read'])->plainTextToken;

    // 23:30 local on Dec 1 is already Dec 2 in UTC; it must still count as Dec 1.
    $this->withToken($token)->getJson('/api/v1/appointments?from=2026-12-01&to=2026-12-01')->assertJsonCount(1, 'data');
    $this->withToken($token)->getJson('/api/v1/appointments?status=cancelled')->assertJsonCount(1, 'data');
});

it('a professional cannot read a colleague appointment', function () {
    $ownersAppointment = Appointment::factory()->for($this->owner, 'staff')->create(['service_id' => $this->service->id]);

    $this->withToken($this->staff->createToken('t', ['appointments:read'])->plainTextToken)
        ->getJson("/api/v1/appointments/{$ownersAppointment->public_id}")
        ->assertForbidden();
});

it('marks a past appointment as completed', function () {
    $past = Appointment::factory()->for($this->staff, 'staff')->at(CarbonImmutable::now()->subHour())->create(['service_id' => $this->service->id]);

    $this->withToken($this->staff->createToken('t', ['appointments:read', 'appointments:write'])->plainTextToken)
        ->patchJson("/api/v1/appointments/{$past->public_id}/status", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('refuses invalid transitions with 422', function () {
    $future = Appointment::factory()->for($this->owner, 'staff')->create(['service_id' => $this->service->id]);
    $token = $this->owner->createToken('t', ['appointments:read', 'appointments:write'])->plainTextToken;

    $this->withToken($token)->patchJson("/api/v1/appointments/{$future->public_id}/status", ['status' => 'no_show'])
        ->assertUnprocessable()->assertJsonPath('code', 'invalid_transition');

    $this->withToken($token)->patchJson("/api/v1/appointments/{$future->public_id}/status", ['status' => 'confirmed'])
        ->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('enforces token abilities', function () {
    $appointment = Appointment::factory()->for($this->owner, 'staff')->create(['service_id' => $this->service->id]);

    $this->withToken($this->owner->createToken('read-only', ['appointments:read'])->plainTextToken)
        ->patchJson("/api/v1/appointments/{$appointment->public_id}/status", ['status' => 'cancelled'])
        ->assertForbidden();
});

it('lists services and revokes the current token', function () {
    $token = $this->owner->createToken('t')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/services')->assertOk()->assertJsonCount(1, 'data');
    $this->withToken($token)->deleteJson('/api/v1/auth/token')->assertNoContent();

    expect($this->owner->tokens()->count())->toBe(0);
});
