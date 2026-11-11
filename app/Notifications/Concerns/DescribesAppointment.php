<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

trait DescribesAppointment
{
    protected function details(MailMessage $mail, Appointment $appointment): MailMessage
    {
        $start = $appointment->localStart()->settings(['locale' => 'es']);

        return $mail
            ->line("**Servicio:** {$appointment->service->name}")
            ->line("**Profesional:** {$appointment->staff->name}")
            ->line('**Cuándo:** '.ucfirst($start->isoFormat('dddd D [de] MMMM, HH:mm')).' hs')
            ->line('**Dónde:** '.($appointment->business->address ?? $appointment->business->name))
            ->line("**Código:** {$appointment->reference()}");
    }

    /**
     * Signed link that lets the customer cancel without an account. It expires at the
     * end of the cancellation window, so the policy is enforced by the URL itself.
     */
    protected function cancelUrl(Appointment $appointment): ?string
    {
        $until = $appointment->cancellableUntil();

        if ($until->lessThanOrEqualTo(CarbonImmutable::now())) {
            return null;
        }

        return URL::temporarySignedRoute('public.appointments.cancel', $until, [
            'business' => $appointment->business->slug,
            'appointment' => $appointment->public_id,
        ]);
    }
}
