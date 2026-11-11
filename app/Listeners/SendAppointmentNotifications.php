<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AppointmentStatus;
use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Events\AppointmentConfirmed;
use App\Notifications\AppointmentCancelledNotification;
use App\Notifications\AppointmentConfirmedNotification;
use Illuminate\Events\Dispatcher;

final class SendAppointmentNotifications
{
    public function onBooked(AppointmentBooked $event): void
    {
        // Bookings that need a deposit are confirmed later, by the payment webhook.
        if ($event->appointment->status === AppointmentStatus::Confirmed) {
            $event->appointment->customer->notify(new AppointmentConfirmedNotification($event->appointment));
        }
    }

    public function onConfirmed(AppointmentConfirmed $event): void
    {
        $event->appointment->customer->notify(new AppointmentConfirmedNotification($event->appointment));
    }

    public function onCancelled(AppointmentCancelled $event): void
    {
        $event->appointment->customer->notify(new AppointmentCancelledNotification($event->appointment));
    }

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            AppointmentBooked::class => 'onBooked',
            AppointmentConfirmed::class => 'onConfirmed',
            AppointmentCancelled::class => 'onCancelled',
        ];
    }
}
