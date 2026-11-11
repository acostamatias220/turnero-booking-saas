<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Booking\BookingCheckout;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\BookingPageController;
use App\Http\Requests\Booking\AvailabilityRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\StaffResource;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use App\Scheduling\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

final class PublicBusinessController extends Controller
{
    public function show(Business $business): JsonResponse
    {
        return response()->json([
            'data' => [
                ...BookingPageController::businessProps($business),
                'services' => ServiceResource::collection(Service::query()->active()->with('staff')->orderBy('name')->get())->resolve(),
                'staff' => StaffResource::collection($business->staff()->get())->resolve(),
            ],
        ]);
    }

    public function availability(AvailabilityRequest $request, Business $business, AvailabilityService $availability): JsonResponse
    {
        [$service, $staff] = $this->resolve($request, $business);
        $date = $request->validated('date') ?? $business->now()->toDateString();

        $slots = $availability->slotsFor($business, $service, $business->localDate($date), $staff);

        return response()->json([
            'data' => [
                'date' => $date,
                'timezone' => $business->timezone,
                'slots' => $slots,
            ],
        ]);
    }

    public function dates(AvailabilityRequest $request, Business $business, AvailabilityService $availability): JsonResponse
    {
        [$service, $staff] = $this->resolve($request, $business);

        return response()->json([
            'data' => [
                'timezone' => $business->timezone,
                'dates' => $availability->availableDates($business, $service, $staff, CarbonImmutable::now()),
            ],
        ]);
    }

    public function book(StoreBookingRequest $request, Business $business, BookingCheckout $checkout): JsonResponse
    {
        ['appointment' => $appointment, 'checkout_url' => $url] = $checkout->handle($business, $request->toBookingRequest());

        $appointment->load(['service', 'staff']);

        return (new AppointmentResource($appointment))
            ->additional(['meta' => [
                'checkout_url' => $url,
                'status_url' => route('public.appointments.show', [$business, $appointment]),
            ]])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @return array{0: Service, 1: User|null}
     */
    private function resolve(AvailabilityRequest $request, Business $business): array
    {
        $service = Service::query()->active()->findOrFail($request->integer('service_id'));
        $staff = $request->filled('staff_id')
            ? $business->staff()->findOrFail($request->integer('staff_id'))
            : null;

        return [$service, $staff];
    }
}
