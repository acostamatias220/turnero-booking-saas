<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Business;
use App\Models\User;
use App\Models\WorkingHour;
use App\Tenancy\TenantContext;

test('registration screen can be rendered', function () {
    $this->get('/register')->assertOk();
});

test('signing up creates the business, its owner and a default schedule', function () {
    $response = $this->post('/register', [
        'business_name' => 'Barbería Don Pepe',
        'category' => 'barbershop',
        'name' => 'Pepe Argento',
        'email' => 'pepe@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $business = Business::query()->where('slug', 'barberia-don-pepe')->firstOrFail();
    $user = User::query()->where('email', 'pepe@example.com')->firstOrFail();

    expect($user->business_id)->toBe($business->id)
        ->and($user->role)->toBe(Role::Owner);

    app(TenantContext::class)->run($business, fn () => expect(WorkingHour::query()->count())->toBe(5));
});

test('slugs are unique across businesses', function () {
    Business::factory()->create(['slug' => 'estudio-luz']);

    $this->post('/register', [
        'business_name' => 'Estudio Luz',
        'category' => 'fitness',
        'name' => 'Lucía',
        'email' => 'lucia@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(Business::query()->where('slug', 'estudio-luz-2')->exists())->toBeTrue();
});
