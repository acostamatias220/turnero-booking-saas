<?php

declare(strict_types=1);

namespace App\Booking;

use App\Models\Appointment;
use App\Models\AppointmentBlock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Writes/releases the 5-minute rows that make overlaps impossible at the DB level.
 */
final class SlotBlocks
{
    /**
     * @throws SlotUnavailableException when any block is already owned
     */
    public function reserve(Appointment $appointment): void
    {
        $rows = [];

        for ($t = $appointment->starts_at; $t->lessThan($appointment->blocked_until); $t = $t->addMinutes(AppointmentBlock::MINUTES)) {
            $rows[] = [
                'staff_id' => $appointment->staff_id,
                'starts_at' => $t->format('Y-m-d H:i:s'),
                'appointment_id' => $appointment->id,
            ];
        }

        try {
            // Savepoint so a conflict does not poison the surrounding transaction.
            DB::transaction(fn () => AppointmentBlock::query()->insert($rows));
        } catch (UniqueConstraintViolationException) {
            throw SlotUnavailableException::make();
        }
    }

    public function release(Appointment $appointment): void
    {
        AppointmentBlock::query()->where('appointment_id', $appointment->id)->delete();
    }
}
