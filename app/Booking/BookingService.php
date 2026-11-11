<?php

declare(strict_types=1);

namespace App\Booking;

use App\Enums\AppointmentStatus;
use App\Events\AppointmentBooked;
use App\Models\Appointment;
use App\Models\AppointmentBlock;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Scheduling\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates appointments without ever double-booking a professional.
 *
 * Three layers, from cheapest to strongest:
 *  1. The availability engine (what the UI shows).
 *  2. `SELECT … FOR UPDATE` on the professional's row: bookings for the same
 *     person are serialised, and the availability check is repeated *after*
 *     acquiring the lock so it sees the previous booking's committed rows.
 *  3. The `appointment_blocks` primary key: if anything above is ever wrong,
 *     InnoDB rejects the overlapping insert and the transaction rolls back.
 */
final class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly SlotBlocks $blocks,
    ) {}

    public function book(Business $business, BookingRequest $request): Appointment
    {
        $service = Service::query()->active()->findOrFail($request->serviceId);

        $this->assertAlignedToBlocks($request->startsAt);

        $candidates = $this->candidatesFor($business, $service, $request);

        foreach ($candidates as $staff) {
            try {
                return $this->bookWith($business, $service, $staff, $request);
            } catch (SlotUnavailableException $e) {
                if ($request->staffId !== null) {
                    throw $e;
                }
                // "Any professional": try the next one who looked free.
            }
        }

        throw SlotUnavailableException::make();
    }

    private function bookWith(Business $business, Service $service, User $staff, BookingRequest $request): Appointment
    {
        $appointment = DB::transaction(function () use ($business, $service, $staff, $request): Appointment {
            // (2) Serialise bookings for this professional. This must be the first
            // read of the transaction so the REPEATABLE READ snapshot is taken after it.
            User::query()->whereKey($staff->id)->lockForUpdate()->firstOrFail();

            $now = CarbonImmutable::now();
            $this->releaseExpiredHolds($staff, $now);

            if (! $this->availability->isFree($business, $service, $staff, $request->startsAt, $now)) {
                throw SlotUnavailableException::make();
            }

            $customer = Customer::query()->createOrFirst(
                ['email' => $request->customerEmail],
                ['name' => $request->customerName, 'phone' => $request->customerPhone],
            );

            $deposit = $business->depositFor($service->price_cents);
            $needsDeposit = $deposit > 0;

            $appointment = new Appointment([
                'staff_id' => $staff->id,
                'service_id' => $service->id,
                'customer_id' => $customer->id,
                'status' => $needsDeposit ? AppointmentStatus::PendingPayment : AppointmentStatus::Confirmed,
                'starts_at' => $request->startsAt,
                'ends_at' => $request->startsAt->addMinutes($service->duration_minutes),
                'blocked_until' => $request->startsAt->addMinutes($service->occupiedMinutes()),
                'price_cents' => $service->price_cents,
                'deposit_cents' => $deposit,
                'hold_expires_at' => $needsDeposit ? $now->addMinutes($business->hold_minutes) : null,
                'notes' => $request->notes,
            ]);
            $appointment->confirmed_at = $needsDeposit ? null : $now;
            $appointment->save();

            // (3) The database has the final word.
            $this->blocks->reserve($appointment);

            return $appointment;
        });

        AppointmentBooked::dispatch($appointment);

        return $appointment;
    }

    /**
     * @return list<User>
     */
    private function candidatesFor(Business $business, Service $service, BookingRequest $request): array
    {
        $staff = $request->staffId !== null
            ? $service->staff()->whereKey($request->staffId)->first()
            : null;

        if ($request->staffId !== null && $staff === null) {
            throw ValidationException::withMessages(['staff_id' => 'Ese profesional no realiza este servicio.']);
        }

        $slots = $this->availability->slotsFor($business, $service, $request->startsAt, $staff);

        foreach ($slots as $slot) {
            if ($slot->startsAt->equalTo($request->startsAt)) {
                return $this->leastBusyFirst($slot->staffIds, $request->startsAt, $business);
            }
        }

        throw SlotUnavailableException::make();
    }

    /**
     * Spread "any professional" bookings evenly: whoever has fewer turnos that day goes first.
     *
     * @param  list<int>  $staffIds
     * @return list<User>
     */
    private function leastBusyFirst(array $staffIds, CarbonImmutable $startsAt, Business $business): array
    {
        $day = $startsAt->setTimezone($business->timezone)->startOfDay();

        $sorted = $business->staff()
            ->whereKey($staffIds)
            ->withCount(['appointments as day_load' => fn ($q) => $q
                ->occupying()
                ->overlapping($day->utc(), $day->addDay()->utc())])
            ->get()
            ->sortBy([['day_load', 'asc'], ['id', 'asc']])
            ->values()
            ->all();

        return array_values($sorted);
    }

    private function releaseExpiredHolds(User $staff, CarbonImmutable $now): void
    {
        $expired = Appointment::query()
            ->where('staff_id', $staff->id)
            ->where('status', AppointmentStatus::PendingPayment->value)
            ->where('hold_expires_at', '<=', $now)
            ->get();

        foreach ($expired as $appointment) {
            $appointment->status = AppointmentStatus::Expired;
            $appointment->save();
            $this->blocks->release($appointment);
        }
    }

    private function assertAlignedToBlocks(CarbonImmutable $startsAt): void
    {
        if ($startsAt->second !== 0 || $startsAt->minute % AppointmentBlock::MINUTES !== 0) {
            throw ValidationException::withMessages(['starts_at' => 'Horario inválido.']);
        }
    }
}
