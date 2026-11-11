<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\AppointmentReminderNotification;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class SendRemindersCommand extends Command
{
    protected $description = 'Queue reminder e-mails for confirmed turnos in the next 24 hours';

    protected $signature = 'appointments:send-reminders {--hours=24 : How far ahead to look}';

    public function handle(TenantContext $tenant): int
    {
        $now = CarbonImmutable::now();
        $sent = 0;

        Appointment::acrossTenants()
            ->with('business') // tenant-owned relations are loaded inside the tenant, below
            ->where('status', AppointmentStatus::Confirmed->value)
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [$now, $now->addHours((int) $this->option('hours'))])
            ->chunkById(200, function ($appointments) use ($tenant, $now, &$sent): void {
                foreach ($appointments as $appointment) {
                    // Claim atomically: if two schedulers overlap, only one wins the UPDATE.
                    $claimed = Appointment::acrossTenants()
                        ->whereKey($appointment->id)
                        ->whereNull('reminder_sent_at')
                        ->update(['reminder_sent_at' => $now]);

                    if ($claimed === 1) {
                        $tenant->run($appointment->business, function () use ($appointment): void {
                            $appointment->load(['customer', 'service', 'staff']);
                            $appointment->customer->notify(new AppointmentReminderNotification($appointment));
                        });
                        $sent++;
                    }
                }
            });

        $this->components->info("Queued {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
