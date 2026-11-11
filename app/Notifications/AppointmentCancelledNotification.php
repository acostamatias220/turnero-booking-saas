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

final class AppointmentCancelledNotification extends Notification implements ShouldQueue
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
            ->subject("Turno cancelado · {$appointment->business->name}")
            ->greeting("Hola {$appointment->customer->name},")
            ->line('Tu turno fue cancelado.');

        $this->details($mail, $appointment);

        if ($appointment->deposit_cents > 0 && $appointment->payment?->status?->value === 'refund_required') {
            $mail->line('Vamos a devolverte la seña en los próximos días hábiles.');
        }

        return $mail
            ->action('Reservar otro horario', route('public.booking', $appointment->business->slug))
            ->attachData(IcsCalendar::forAppointment($appointment, 'CANCELLED'), 'turno.ics', ['mime' => 'text/calendar']);
    }
}
