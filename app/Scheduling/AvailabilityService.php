<?php

declare(strict_types=1);

namespace App\Scheduling;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Holiday;
use App\Models\Service;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Loads schedules from the database and turns them into bookable slots.
 *
 * Wall-clock working hours ("09:00–18:00", business timezone) are converted to
 * UTC per calendar day, so a day that crosses a DST change correctly has 23 or
 * 25 hours and no slot is generated in a non-existent local hour.
 */
final class AvailabilityService
{
    public function __construct(private readonly SlotFinder $finder) {}

    /**
     * @param  CarbonImmutable  $day  any instant of the local calendar day
     * @return list<Slot>
     */
    public function slotsFor(Business $business, Service $service, CarbonImmutable $day, ?User $staff = null, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $localDay = $day->setTimezone($business->timezone)->startOfDay();

        if (! $this->isWithinHorizon($business, $localDay, $now) || $this->isHoliday($localDay)) {
            return [];
        }

        $candidates = $this->candidateStaff($service, $staff);
        $notBefore = $now->addMinutes($business->min_notice_minutes);

        /** @var array<string, list<int>> $byStart */
        $byStart = [];

        foreach ($candidates as $member) {
            $starts = $this->finder->find(
                working: $this->workingIntervals($member, $localDay),
                busy: $this->busyIntervals($member, $localDay, $now),
                occupiedMinutes: $service->occupiedMinutes(),
                stepMinutes: $business->slot_interval_minutes,
                notBefore: $notBefore,
            );

            foreach ($starts as $start) {
                $byStart[$start->toIso8601ZuluString()][] = $member->id;
            }
        }

        ksort($byStart);

        $slots = [];

        foreach ($byStart as $iso => $staffIds) {
            $start = CarbonImmutable::parse($iso)->utc();
            $slots[] = new Slot($start, $start->addMinutes($service->duration_minutes), $staffIds, $business->timezone);
        }

        return $slots;
    }

    /**
     * Is this exact start free for this professional? Used again inside the booking
     * transaction, after the row lock, so the check sees committed data.
     */
    public function isFree(Business $business, Service $service, User $staff, CarbonImmutable $startsAt, ?CarbonImmutable $now = null): bool
    {
        $slots = $this->slotsFor($business, $service, $startsAt, $staff, $now);

        foreach ($slots as $slot) {
            if ($slot->startsAt->equalTo($startsAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Days in the horizon that have at least one slot (for the date picker).
     *
     * @return list<string> local Y-m-d dates
     */
    public function availableDates(Business $business, Service $service, ?User $staff = null, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $day = $now->setTimezone($business->timezone)->startOfDay();
        $dates = [];

        for ($i = 0; $i < $business->booking_horizon_days; $i++) {
            $current = $day->addDays($i);

            if ($this->slotsFor($business, $service, $current, $staff, $now) !== []) {
                $dates[] = $current->toDateString();
            }
        }

        return $dates;
    }

    /**
     * @return Collection<int, User>
     */
    private function candidateStaff(Service $service, ?User $staff): Collection
    {
        $staffQuery = $service->staff();

        if ($staff !== null) {
            $staffQuery->whereKey($staff->id);
        }

        return $staffQuery->get();
    }

    private function isWithinHorizon(Business $business, CarbonImmutable $localDay, CarbonImmutable $now): bool
    {
        $today = $now->setTimezone($business->timezone)->startOfDay();

        return $localDay->greaterThanOrEqualTo($today)
            && $localDay->lessThan($today->addDays($business->booking_horizon_days));
    }

    private function isHoliday(CarbonImmutable $localDay): bool
    {
        return Holiday::query()->whereDate('date', $localDay->toDateString())->exists();
    }

    private function workingIntervals(User $staff, CarbonImmutable $localDay): IntervalSet
    {
        $date = $localDay->toDateString();
        $tz = $localDay->getTimezone();

        $intervals = WorkingHour::query()
            ->where('user_id', $staff->id)
            ->where('weekday', $localDay->isoWeekday())
            ->get()
            ->map(fn (WorkingHour $wh): Interval => new Interval(
                CarbonImmutable::parse("{$date} {$wh->starts_at}", $tz)->utc(),
                CarbonImmutable::parse("{$date} {$wh->ends_at}", $tz)->utc(),
            ));

        return new IntervalSet($intervals);
    }

    private function busyIntervals(User $staff, CarbonImmutable $localDay, CarbonImmutable $now): IntervalSet
    {
        $from = $localDay->utc();
        $to = $localDay->addDay()->utc();

        $appointments = Appointment::query()
            ->where('staff_id', $staff->id)
            ->occupying($now)
            ->overlapping($from, $to)
            ->get(['starts_at', 'blocked_until'])
            ->map(fn (Appointment $a): Interval => new Interval($a->starts_at, $a->blocked_until));

        $timeOff = TimeOff::query()
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $staff->id))
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get(['starts_at', 'ends_at'])
            ->map(fn (TimeOff $t): Interval => new Interval($t->starts_at, $t->ends_at));

        return new IntervalSet($appointments->concat($timeOff));
    }
}
