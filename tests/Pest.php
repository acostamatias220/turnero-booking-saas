<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkingHour;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->group('concurrency')
    ->in('Concurrency');

/*
| Helpers shared by the feature tests.
*/

/**
 * A ready-to-book business: one owner/professional working Mon–Fri 09:00–13:00 and
 * 14:00–18:00 (lunch break) and one 30-minute service.
 *
 * @return array{business: Business, owner: User, service: Service}
 */
function bookableBusiness(array $businessAttributes = [], array $serviceAttributes = []): array
{
    $business = Business::factory()->create($businessAttributes);
    $owner = User::factory()->forBusiness($business)->create(['role' => Role::Owner, 'name' => 'Ana Dueña']);

    app(TenantContext::class)->run($business, function () use ($owner) {
        foreach (range(1, 5) as $weekday) {
            WorkingHour::factory()->create(['user_id' => $owner->id, 'weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '13:00']);
            WorkingHour::factory()->create(['user_id' => $owner->id, 'weekday' => $weekday, 'starts_at' => '14:00', 'ends_at' => '18:00']);
        }
    });

    $service = Service::factory()->create(['business_id' => $business->id, 'name' => 'Corte', ...$serviceAttributes]);
    $service->staff()->attach($owner);

    return ['business' => $business, 'owner' => $owner, 'service' => $service];
}

/** Add a professional with the same schedule who also performs the service. */
function addProfessional(Business $business, Service $service, string $name = 'Bruno Profesional'): User
{
    $staff = User::factory()->forBusiness($business)->staff()->create(['name' => $name]);

    app(TenantContext::class)->run($business, function () use ($staff) {
        foreach (range(1, 5) as $weekday) {
            WorkingHour::factory()->create(['user_id' => $staff->id, 'weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '18:00']);
        }
    });

    $service->staff()->attach($staff);

    return $staff;
}

/** A local wall-clock time in Buenos Aires on the next Monday, as a UTC instant. */
function nextMondayAt(string $time, string $tz = 'America/Argentina/Buenos_Aires'): CarbonImmutable
{
    return CarbonImmutable::parse("next monday {$time}", $tz)->utc();
}
