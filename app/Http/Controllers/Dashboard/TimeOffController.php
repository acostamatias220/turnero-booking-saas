<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\TimeOff;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vacations / errands (time off) and closed days (holidays).
 */
final class TimeOffController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $this->authorize('manage-business');
        $business = $tenant->get();
        $tz = $business->timezone;

        return Inertia::render('time-off/index', [
            'time_off' => TimeOff::query()
                ->with('user:id,name')
                ->where('ends_at', '>=', now())
                ->orderBy('starts_at')
                ->get()
                ->map(fn (TimeOff $t): array => [
                    'id' => $t->id,
                    'who' => $t->user->name ?? 'Todo el negocio',
                    'starts_at' => $t->starts_at->setTimezone($tz)->format('Y-m-d H:i'),
                    'ends_at' => $t->ends_at->setTimezone($tz)->format('Y-m-d H:i'),
                    'reason' => $t->reason,
                ]),
            'holidays' => Holiday::query()
                ->whereDate('date', '>=', $business->now()->toDateString())
                ->orderBy('date')
                ->get()
                ->map(fn (Holiday $h): array => [
                    'id' => $h->id,
                    'date' => $h->date->toDateString(),
                    'label' => ucfirst($h->date->settings(['locale' => 'es'])->isoFormat('dddd D [de] MMMM')),
                    'name' => $h->name,
                ]),
            'staff' => $business->staff()->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, TenantContext $tenant): RedirectResponse
    {
        $this->authorize('manage-business');
        $business = $tenant->get();

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('business_id', $business->id)],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);

        // The form speaks local wall-clock time; we persist instants.
        TimeOff::query()->create([
            'user_id' => $data['user_id'] ?? null,
            'starts_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['starts_at'], $business->timezone)?->utc(),
            'ends_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $data['ends_at'], $business->timezone)?->utc(),
            'reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', 'Ausencia registrada. Esos horarios ya no se ofrecen.');
    }

    public function destroy(TimeOff $timeOff): RedirectResponse
    {
        $this->authorize('manage-business');
        $timeOff->delete();

        return back()->with('success', 'Ausencia eliminada.');
    }

    public function storeHoliday(Request $request, TenantContext $tenant): RedirectResponse
    {
        $this->authorize('manage-business');

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('holidays', 'date')->where('business_id', $tenant->id())],
            'name' => ['required', 'string', 'max:120'],
        ]);

        Holiday::query()->create($data);

        return back()->with('success', 'Día cerrado agregado.');
    }

    public function destroyHoliday(Holiday $holiday): RedirectResponse
    {
        $this->authorize('manage-business');
        $holiday->delete();

        return back()->with('success', 'Día cerrado eliminado.');
    }
}
