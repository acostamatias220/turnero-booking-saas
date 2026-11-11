<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Booking\StaffStatusChanger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateAppointmentStatusRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\User;
use App\Queries\AppointmentFilter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AppointmentController extends Controller
{
    public function index(Request $request, AppointmentFilter $filter): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Appointment::class);

        /** @var User $user */
        $user = $request->user();
        abort_unless($user->tokenCan('appointments:read'), 403);

        $appointments = $filter->apply(Appointment::query(), $request, $user)
            ->with(['business', 'service', 'staff', 'customer'])
            ->orderBy('starts_at')
            ->paginate(min(100, max(1, $request->integer('per_page', 25))))
            ->withQueryString();

        return AppointmentResource::collection($appointments);
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        $this->authorize('view', $appointment);

        return new AppointmentResource($appointment->load(['business', 'service', 'staff', 'customer', 'payment']));
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment, StaffStatusChanger $changer): AppointmentResource
    {
        $this->authorize('update', $appointment);

        /** @var User $user */
        $user = $request->user();
        abort_unless($user->tokenCan('appointments:write'), 403);

        $changer->change($appointment, $request->status());

        return new AppointmentResource($appointment->refresh()->load(['business', 'service', 'staff', 'customer']));
    }
}
