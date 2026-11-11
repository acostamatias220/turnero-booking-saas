<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\User;
use App\Models\WorkingHour;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenant): Response
    {
        /** @var User $user */
        $user = $request->user();
        $business = $tenant->get();
        $now = $business->now();
        $today = $now->startOfDay();
        $weekStart = $now->startOfWeek();
        $weekEnd = $weekStart->addWeek();

        $scope = fn (): Builder => Appointment::query()
            ->when(! $user->isOwner(), fn (Builder $q) => $q->where('staff_id', $user->id));

        $billable = [AppointmentStatus::Confirmed->value, AppointmentStatus::Completed->value];

        $todayCount = $scope()
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::Expired->value])
            ->whereBetween('starts_at', [$today->utc(), $today->addDay()->utc()])
            ->count();

        $weekRevenue = (int) $scope()
            ->whereIn('status', $billable)
            ->whereBetween('starts_at', [$weekStart->utc(), $weekEnd->utc()])
            ->sum('price_cents');

        $bookedMinutes = (int) $scope()
            ->whereIn('status', $billable)
            ->whereBetween('starts_at', [$weekStart->utc(), $weekEnd->utc()])
            ->get(['starts_at', 'ends_at'])
            ->sum(fn (Appointment $a): int => (int) $a->starts_at->diffInMinutes($a->ends_at));

        $workingMinutes = (int) WorkingHour::query()
            ->when(! $user->isOwner(), fn (Builder $q) => $q->where('user_id', $user->id))
            ->get(['starts_at', 'ends_at'])
            ->sum(fn (WorkingHour $wh): int => (int) CarbonImmutable::parse($wh->starts_at)->diffInMinutes(CarbonImmutable::parse($wh->ends_at)));

        $last30 = $scope()
            ->whereBetween('starts_at', [$today->subDays(30)->utc(), $now->utc()])
            ->whereIn('status', [AppointmentStatus::Completed->value, AppointmentStatus::NoShow->value])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $attended = (int) ($last30[AppointmentStatus::Completed->value] ?? 0);
        $noShows = (int) ($last30[AppointmentStatus::NoShow->value] ?? 0);

        $upcoming = $scope()
            ->with(['business', 'service', 'staff', 'customer'])
            ->whereIn('status', AppointmentStatus::activeValues())
            ->where('starts_at', '>=', $now->utc())
            ->orderBy('starts_at')
            ->limit(6)
            ->get();

        return Inertia::render('dashboard', [
            'kpis' => [
                'today' => $todayCount,
                'week_revenue' => Money::format($weekRevenue, $business->currency),
                'occupancy' => $workingMinutes > 0 ? (int) round(100 * $bookedMinutes / $workingMinutes) : 0,
                'no_show_rate' => ($attended + $noShows) > 0 ? (int) round(100 * $noShows / ($attended + $noShows)) : 0,
            ],
            'week' => $this->nextSevenDays($scope, $today),
            'top_services' => $this->topServices($scope, $today),
            'upcoming' => AppointmentResource::collection($upcoming)->resolve(),
            'public_url' => route('public.booking', $business),
        ]);
    }

    /**
     * @param  callable(): Builder<Appointment>  $scope
     * @return list<array{date: string, label: string, total: int}>
     */
    private function nextSevenDays(callable $scope, CarbonImmutable $today): array
    {
        $rows = $scope()
            ->whereIn('status', AppointmentStatus::activeValues())
            ->whereBetween('starts_at', [$today->utc(), $today->addDays(7)->utc()])
            ->get(['starts_at'])
            ->countBy(fn (Appointment $a): string => $a->starts_at->setTimezone($today->getTimezone())->toDateString());

        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $today->addDays($i);
            $days[] = [
                'date' => $day->toDateString(),
                'label' => ucfirst($day->settings(['locale' => 'es'])->isoFormat('ddd D')),
                'total' => (int) ($rows[$day->toDateString()] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * @param  callable(): Builder<Appointment>  $scope
     * @return list<array{name: string, total: int}>
     */
    private function topServices(callable $scope, CarbonImmutable $today): array
    {
        $rows = $scope()
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->whereNotIn('appointments.status', [AppointmentStatus::Cancelled->value, AppointmentStatus::Expired->value])
            ->where('appointments.starts_at', '>=', $today->subDays(30)->utc())
            ->selectRaw('services.name as name, count(*) as total')
            ->groupBy('services.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => ['name' => (string) $row->getAttribute('name'), 'total' => (int) $row->getAttribute('total')])
            ->all();

        return array_values($rows);
    }
}
