<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Holiday;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\WorkingHour;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Day view: one column per professional, appointments positioned by local time.
 */
final class AgendaController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenant): Response
    {
        /** @var User $user */
        $user = $request->user();
        $business = $tenant->get();

        $dateParam = (string) $request->string('date');
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam) === 1
            ? $business->localDate($dateParam)
            : $business->now()->startOfDay();

        $staff = $user->isOwner()
            ? $business->staff()->get()
            : collect([$user]);
        $staffIds = $staff->pluck('id');

        $appointments = Appointment::query()
            ->with(['business', 'service', 'staff', 'customer', 'payment'])
            ->whereIn('staff_id', $staffIds)
            ->whereNotIn('status', ['expired'])
            ->whereBetween('starts_at', [$day->utc(), $day->addDay()->utc()])
            ->orderBy('starts_at')
            ->get();

        $hours = WorkingHour::query()
            ->whereIn('user_id', $staffIds)
            ->where('weekday', $day->isoWeekday())
            ->get();

        $timeOff = TimeOff::query()
            ->where(fn ($q) => $q->whereNull('user_id')->orWhereIn('user_id', $staffIds))
            ->where('starts_at', '<', $day->addDay()->utc())
            ->where('ends_at', '>', $day->utc())
            ->get();

        $startHour = (int) min(8, ...$hours->map(fn (WorkingHour $h): int => (int) substr($h->starts_at, 0, 2))->all() ?: [8]);
        $endHour = (int) max(20, ...$hours->map(fn (WorkingHour $h): int => (int) ceil(((int) substr($h->ends_at, 0, 2) * 60 + (int) substr($h->ends_at, 3, 2)) / 60))->all() ?: [20]);

        $tz = $business->timezone;
        $minutesOf = fn (CarbonImmutable $t): int => max(0, min(1440, (int) $day->diffInMinutes($t->setTimezone($tz), false)));

        return Inertia::render('agenda', [
            'date' => $day->toDateString(),
            'date_label' => ucfirst($day->settings(['locale' => 'es'])->isoFormat('dddd D [de] MMMM')),
            'is_today' => $day->isSameDay($business->now()),
            'holiday' => Holiday::query()->whereDate('date', $day->toDateString())->value('name'),
            'range' => ['start' => $startHour, 'end' => $endHour],
            'staff' => $staff->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'title' => $member->title,
                'working' => $hours->where('user_id', $member->id)->map(fn (WorkingHour $h): array => [
                    'start' => substr($h->starts_at, 0, 5),
                    'end' => substr($h->ends_at, 0, 5),
                ])->values(),
                'time_off' => $timeOff->filter(fn (TimeOff $t): bool => $t->user_id === null || $t->user_id === $member->id)
                    ->map(fn (TimeOff $t): array => [
                        'start_minute' => $minutesOf($t->starts_at),
                        'end_minute' => $minutesOf($t->ends_at),
                        'reason' => $t->reason,
                    ])->values(),
            ])->values(),
            'appointments' => $appointments->map(fn (Appointment $a): array => [
                ...(new AppointmentResource($a))->resolve(),
                'start_minute' => $minutesOf($a->starts_at),
                'end_minute' => $minutesOf($a->ends_at),
            ])->values(),
        ]);
    }
}
