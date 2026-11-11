<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Booking\AppointmentLifecycle;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Business;
use App\Support\IcsCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pages reachable by the customer without an account. The appointment is addressed
 * by its ULID (unguessable) and cancellation additionally requires a signed URL.
 */
final class AppointmentStatusController extends Controller
{
    public function show(Business $business, Appointment $appointment): Response
    {
        $appointment->load(['service', 'staff', 'payment']);

        return Inertia::render('public/appointment', [
            'business' => BookingPageController::businessProps($business),
            'appointment' => (new AppointmentResource($appointment))->resolve(),
        ]);
    }

    public function calendar(Business $business, Appointment $appointment): HttpResponse
    {
        $appointment->load(['service', 'staff']);

        return response(IcsCalendar::forAppointment($appointment), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="turno-'.$appointment->reference().'.ics"',
        ]);
    }

    public function confirmCancel(Request $request, Business $business, Appointment $appointment): Response
    {
        $appointment->load(['service', 'staff']);

        return Inertia::render('public/cancel', [
            'business' => BookingPageController::businessProps($business),
            'appointment' => (new AppointmentResource($appointment))->resolve(),
            'can_cancel' => $appointment->canBeCancelledByCustomer(),
            'action' => $request->fullUrl(),
        ]);
    }

    public function cancel(Business $business, Appointment $appointment, AppointmentLifecycle $lifecycle): RedirectResponse
    {
        if (! $appointment->canBeCancelledByCustomer()) {
            return back()->with('error', 'Este turno ya no se puede cancelar online. Comunicate con el negocio.');
        }

        $lifecycle->cancel($appointment, 'customer', byCustomer: true);

        return to_route('public.appointments.show', [$business, $appointment])
            ->with('success', 'Cancelamos tu turno. ¡Gracias por avisar!');
    }
}
