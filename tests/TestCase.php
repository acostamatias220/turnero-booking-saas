<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Business;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /** Activate a tenant for code that runs outside an HTTP request (services, jobs). */
    protected function actingAsTenant(Business $business): static
    {
        app(TenantContext::class)->set($business);

        return $this;
    }

    protected function forgetTenant(): static
    {
        app(TenantContext::class)->set(null);

        return $this;
    }
}
