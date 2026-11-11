<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Concerns\DescribesAppointment;
use App\Support\IcsCalendar;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AppointmentConfirmedNotification extends Notification implements ShouldQueue
{
    use DescribesAppointment;
    use Queueable;

    public function __construct(public readonly Appointment $appointment)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment;
        $mail = (new MailMessage)
            ->subject("Turno confirmado · {$appointment->business->name}")
            ->greeting("¡Hola {$appointment->customer->name}!")
            ->line('Tu turno quedó confirmado. Te esperamos.');

        $this->details($mail, $appointment);

        $mail->action('Ver mi turno', route('public.appointments.show', [$appointment->business->slug, $appointment->public_id]));

        if (($url = $this->cancelUrl($appointment)) !== null) {
            $mail->line("¿No podés venir? Podés cancelar hasta {$appointment->business->cancellation_notice_hours} h antes: [cancelar turno]({$url})");
        }

        return $mail->attachData(IcsCalendar::forAppointment($appointment), 'turno.ics', ['mime' => 'text/calendar']);
    }
}
