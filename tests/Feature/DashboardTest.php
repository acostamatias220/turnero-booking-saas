<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('owners see business-wide KPIs and upcoming turnos', function () {
    ['business' => $business, 'owner' => $owner, 'service' => $service] = bookableBusiness();
    $staff = addProfessional($business, $service);

    Appointment::factory()->for($owner, 'staff')->at(CarbonImmutable::now()->addHours(3))->create(['service_id' => $service->id]);
    Appointment::factory()->for($staff, 'staff')->at(CarbonImmutable::now()->addHours(5))->create(['service_id' => $service->id]);

    $this->actingAs($owner)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('upcoming', 2)
            ->has('week', 7)
            ->has('kpis.week_revenue')
            ->where('public_url', route('public.booking', $business)));
});

test('professionals only see their own numbers', function () {
    ['business' => $business, 'owner' => $owner, 'service' => $service] = bookableBusiness();
    $staff = addProfessional($business, $service);

    Appointment::factory()->for($owner, 'staff')->at(CarbonImmutable::now()->addHours(3))->create(['service_id' => $service->id]);
    Appointment::factory()->for($staff, 'staff')->at(CarbonImmutable::now()->addHours(5))->create(['service_id' => $service->id]);

    $this->actingAs($staff)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->has('upcoming', 1)->where('upcoming.0.staff.id', $staff->id));
});

test('a user without a business is rejected', function () {
    $user = User::factory()->create(['business_id' => null]);

    $this->actingAs($user)->get('/dashboard')->assertForbidden();
});
