<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Tenancy\CrossTenantWriteException;
use App\Tenancy\MissingTenantException;
use App\Tenancy\TenantContext;

beforeEach(function () {
    $this->a = bookableBusiness(['name' => 'Barbería A']);
    $this->b = bookableBusiness(['name' => 'Barbería B']);
});

it('fails closed: querying a tenant model without a tenant throws', function () {
    Service::query()->get();
})->throws(MissingTenantException::class);

it('only returns rows of the active tenant', function () {
    $this->actingAsTenant($this->a['business']);

    expect(Service::query()->pluck('id')->all())->toBe([$this->a['service']->id])
        ->and(Service::query()->find($this->b['service']->id))->toBeNull();
});

it('fills business_id automatically from the active tenant', function () {
    $this->actingAsTenant($this->a['business']);

    $customer = Customer::query()->create(['name' => 'Juan', 'email' => 'juan@example.com']);

    expect($customer->business_id)->toBe($this->a['business']->id);
});

it('refuses to write a row into another tenant', function () {
    $this->actingAsTenant($this->a['business']);

    $customer = new Customer(['name' => 'Intruso', 'email' => 'x@example.com']);
    $customer->business_id = $this->b['business']->id;
    $customer->save();
})->throws(CrossTenantWriteException::class);

it('lets system code opt out explicitly with acrossTenants()', function () {
    expect(Service::acrossTenants()->count())->toBe(2);
});

it('restores the outer tenant after a nested run()', function () {
    $context = app(TenantContext::class);
    $context->set($this->a['business']);

    $inner = $context->run($this->b['business'], fn () => Service::query()->value('id'));

    expect($inner)->toBe($this->b['service']->id)
        ->and($context->id())->toBe($this->a['business']->id);
});

it('the same e-mail in two businesses is two different customers', function () {
    app(TenantContext::class)->run($this->a['business'], fn () => Customer::query()->create(['name' => 'Juan', 'email' => 'juan@example.com']));
    app(TenantContext::class)->run($this->b['business'], fn () => Customer::query()->create(['name' => 'Juan', 'email' => 'juan@example.com']));

    expect(Customer::acrossTenants()->where('email', 'juan@example.com')->count())->toBe(2);
});

describe('over HTTP', function () {
    beforeEach(function () {
        $this->foreign = Appointment::factory()->for($this->b['owner'], 'staff')->create(['service_id' => $this->b['service']->id]);
    });

    it('404s when an owner pokes at another business appointment (web)', function () {
        $this->actingAs($this->a['owner'])
            ->patch(route('appointments.status', $this->foreign->public_id), ['status' => 'cancelled'])
            ->assertNotFound();

        expect($this->foreign->fresh()?->status->value)->toBe('confirmed');
    });

    it('404s for another business service', function () {
        $this->actingAs($this->a['owner'])
            ->put(route('services.update', $this->b['service']->id), ['name' => 'Hackeado', 'duration_minutes' => 30, 'buffer_minutes' => 0, 'price' => 1])
            ->assertNotFound();
    });

    it('404s when an API token of business A reads business B data', function () {
        $token = $this->a['owner']->createToken('test', ['appointments:read'])->plainTextToken;

        $this->withToken($token)->getJson("/api/v1/appointments/{$this->foreign->public_id}")->assertNotFound();
        $this->withToken($token)->getJson('/api/v1/appointments')->assertOk()->assertJsonCount(0, 'data');
    });

    it('does not let an owner assign staff from another business to a service', function () {
        $this->actingAs($this->a['owner'])
            ->post(route('services.store'), [
                'name' => 'Nuevo', 'duration_minutes' => 30, 'buffer_minutes' => 0, 'price' => 1000,
                'staff_ids' => [$this->b['owner']->id],
            ])
            ->assertSessionHasErrors('staff_ids.0');
    });

    it('does not let an owner edit a member of another business', function () {
        $this->actingAs($this->a['owner'])
            ->put(route('team.update', $this->b['owner']->id), ['name' => 'X', 'email' => 'x@example.com'])
            ->assertNotFound();
    });

    it('public booking pages are isolated by slug', function () {
        $this->getJson("/api/v1/public/businesses/{$this->a['business']->slug}")
            ->assertOk()
            ->assertJsonCount(1, 'data.services')
            ->assertJsonPath('data.services.0.id', $this->a['service']->id);

        $this->getJson("/api/v1/public/businesses/{$this->a['business']->slug}/availability?service_id={$this->b['service']->id}")
            ->assertNotFound();
    });
});

arch('every tenant-owned model uses the BelongsToBusiness trait')
    ->expect([
        Service::class,
        App\Models\WorkingHour::class,
        App\Models\TimeOff::class,
        App\Models\Holiday::class,
        Customer::class,
        Appointment::class,
        App\Models\Payment::class,
    ])
    ->toUseTrait(App\Tenancy\BelongsToBusiness::class);

it('bypasses the tenant scope only through the explicit acrossTenants() API', function () {
    $offenders = collect(Symfony\Component\Finder\Finder::create()->in(app_path())->name('*.php'))
        ->filter(fn ($file) => str_contains($file->getContents(), 'withoutGlobalScope'))
        ->map(fn ($file) => str_replace(base_path().'/', '', $file->getRealPath()))
        ->values()
        ->all();

    expect($offenders)->toBe(['app/Tenancy/BelongsToBusiness.php']);
});

it('Business and User are deliberately not tenant-scoped', function () {
    expect(Business::query()->count())->toBe(2)
        ->and(User::query()->count())->toBe(2);
});
