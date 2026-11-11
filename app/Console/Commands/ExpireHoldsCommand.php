<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Booking\AppointmentLifecycle;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class ExpireHoldsCommand extends Command
{
    protected $signature = 'appointments:expire-holds';

    protected $description = 'Release slots held for deposits that were never paid';

    public function handle(AppointmentLifecycle $lifecycle, TenantContext $tenant): int
    {
        $expired = 0;

        Appointment::acrossTenants()
            ->with('business')
            ->where('status', AppointmentStatus::PendingPayment->value)
            ->where('hold_expires_at', '<=', CarbonImmutable::now())
            ->chunkById(200, function ($appointments) use ($lifecycle, $tenant, &$expired): void {
                foreach ($appointments as $appointment) {
                    $tenant->run($appointment->business, fn () => $lifecycle->expire($appointment));
                    $expired++;
                }
            });

        $this->components->info("Released {$expired} expired hold(s).");

        return self::SUCCESS;
    }
}
