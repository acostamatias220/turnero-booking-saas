<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Concerns\DescribesAppointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AppointmentReminderNotification extends Notification implements ShouldQueue
{
    use DescribesAppointment;
    use Queueable;

    public function __construct(public readonly Appointment $appointment) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment;
        $mail = (new MailMessage)
            ->subject("Recordatorio: mañana tenés turno en {$appointment->business->name}")
            ->greeting("¡Hola {$appointment->customer->name}!")
            ->line('Te recordamos tu próximo turno:');

        $this->details($mail, $appointment);

        if (($url = $this->cancelUrl($appointment)) !== null) {
            $mail->line("Si no podés asistir, avisanos así otra persona puede usar el horario: [cancelar turno]({$url})");
        }

        return $mail;
    }
}
