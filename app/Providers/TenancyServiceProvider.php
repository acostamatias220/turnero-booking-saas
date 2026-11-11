<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Business;
use App\Tenancy\TenantContext;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

/**
 * Carries the active tenant across the queue boundary.
 *
 * Every queued payload is stamped with `tenant_id`; the worker restores it before
 * the job runs and pops it afterwards. The push/pop stack matters with the `sync`
 * driver (tests, local dev): there the job runs *inside* the HTTP request, and a
 * naive "forget tenant after job" would wipe the request's tenant halfway through.
 */
class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        Queue::createPayloadUsing(fn (): array => [
            'tenant_id' => $this->app->make(TenantContext::class)->id(),
        ]);

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $tenantId = $event->job->payload()['tenant_id'] ?? null;

            $this->app->make(TenantContext::class)->push(
                $tenantId === null ? null : Business::query()->find($tenantId),
            );
        });

        $restore = fn () => $this->app->make(TenantContext::class)->pop();

        Event::listen(JobProcessed::class, $restore);
        Event::listen(JobExceptionOccurred::class, $restore);
    }
}
