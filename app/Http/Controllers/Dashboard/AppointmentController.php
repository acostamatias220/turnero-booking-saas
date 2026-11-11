<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Booking\StaffStatusChanger;
use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateAppointmentStatusRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\User;
use App\Queries\AppointmentFilter;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AppointmentController extends Controller
{
    public function index(Request $request, AppointmentFilter $filter, TenantContext $tenant): Response
    {
        $this->authorize('viewAny', Appointment::class);

        /** @var User $user */
        $user = $request->user();

        $appointments = $filter->apply(Appointment::query(), $request, $user)
            ->with(['business', 'service', 'staff', 'customer', 'payment'])
            ->orderByDesc('starts_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('appointments/index', [
            'appointments' => AppointmentResource::collection($appointments),
            'filters' => $request->only(['status', 'staff_id', 'from', 'to', 'q']),
            'statuses' => collect(AppointmentStatus::cases())->map(fn (AppointmentStatus $s): array => ['value' => $s->value, 'label' => $s->label()]),
            'staff' => $user->isOwner()
                ? $tenant->get()->staff()->get(['id', 'name'])
                : [],
        ]);
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment, StaffStatusChanger $changer): RedirectResponse
    {
        $this->authorize('update', $appointment);

        $changer->change($appointment, $request->status());

        return back()->with('success', "Turno {$appointment->reference()}: {$appointment->status->label()}.");
    }
}
