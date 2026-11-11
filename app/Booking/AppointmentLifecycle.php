<?php

declare(strict_types=1);

namespace App\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Events\AppointmentCancelled;
use App\Events\AppointmentConfirmed;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Every status change goes through here so the state machine, the slot blocks
 * and the domain events can never drift apart.
 */
final class AppointmentLifecycle
{
    public function __construct(private readonly SlotBlocks $blocks) {}

    /**
     * Deposit received. Idempotent: confirming an already confirmed appointment is a no-op.
     *
     * @return bool whether the appointment ended up confirmed
     */
    public function confirmPaid(Appointment $appointment): bool
    {
        $confirmed = DB::transaction(function () use ($appointment): bool {
            $locked = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === AppointmentStatus::Confirmed) {
                return false;
            }

            if ($locked->status === AppointmentStatus::Expired) {
                // Paid after the hold expired: try to get the slot back. If someone else
                // already took it, the caller flags the payment for a refund.
                $this->blocks->reserve($locked);
            } elseif ($locked->status !== AppointmentStatus::PendingPayment) {
                throw InvalidTransitionException::between($locked->status, AppointmentStatus::Confirmed);
            }

            $locked->status = AppointmentStatus::Confirmed;
            $locked->confirmed_at = CarbonImmutable::now();
            $locked->hold_expires_at = null;
            $locked->save();

            $appointment->setRawAttributes($locked->getAttributes(), true);

            return true;
        });

        if ($confirmed) {
            AppointmentConfirmed::dispatch($appointment);
        }

        return $appointment->status === AppointmentStatus::Confirmed;
    }

    public function cancel(Appointment $appointment, string $reason, bool $byCustomer = false): void
    {
        DB::transaction(function () use ($appointment, $reason): void {
            $this->transition($appointment, AppointmentStatus::Cancelled);
            $appointment->cancelled_at = CarbonImmutable::now();
            $appointment->cancellation_reason = $reason;
            $appointment->save();
            $this->blocks->release($appointment);

            $payment = $appointment->payment;

            if ($payment !== null && $payment->status === PaymentStatus::Approved) {
                $payment->status = PaymentStatus::RefundRequired;
                $payment->save();
            }
        });

        AppointmentCancelled::dispatch($appointment, $byCustomer);
    }

    public function expire(Appointment $appointment): void
    {
        DB::transaction(function () use ($appointment): void {
            $this->transition($appointment, AppointmentStatus::Expired);
            $appointment->save();
            $this->blocks->release($appointment);
        });
    }

    public function markCompleted(Appointment $appointment): void
    {
        $this->assertStarted($appointment);
        $this->transition($appointment, AppointmentStatus::Completed);
        $appointment->save();
    }

    public function markNoShow(Appointment $appointment): void
    {
        $this->assertStarted($appointment);
        $this->transition($appointment, AppointmentStatus::NoShow);
        $appointment->save();
    }

    private function transition(Appointment $appointment, AppointmentStatus $to): void
    {
        if (! $appointment->status->canTransitionTo($to)) {
            throw InvalidTransitionException::between($appointment->status, $to);
        }

        $appointment->status = $to;
    }

    private function assertStarted(Appointment $appointment): void
    {
        if ($appointment->starts_at->isFuture()) {
            throw new InvalidTransitionException('Solo se puede marcar un turno que ya empezó.');
        }
    }
}
