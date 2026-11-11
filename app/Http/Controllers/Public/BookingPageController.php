<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Booking\BookingCheckout;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\StaffResource;
use App\Models\Business;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class BookingPageController extends Controller
{
    public function show(Business $business): Response
    {
        $services = Service::query()->active()->with('staff')->orderBy('name')->get();

        return Inertia::render('public/booking', [
            'business' => self::businessProps($business),
            'services' => ServiceResource::collection($services)->resolve(),
            'staff' => StaffResource::collection($business->staff()->get())->resolve(),
        ]);
    }

    public function store(StoreBookingRequest $request, Business $business, BookingCheckout $checkout): RedirectResponse|HttpResponse
    {
        ['appointment' => $appointment, 'checkout_url' => $url] = $checkout->handle($business, $request->toBookingRequest());

        if ($url !== null) {
            // External redirect (the provider's hosted checkout), not an Inertia visit.
            return Inertia::location($url);
        }

        return to_route('public.appointments.show', [$business, $appointment])
            ->with('success', '¡Listo! Tu turno quedó confirmado.');
    }

    /** @return array<string, mixed> */
    public static function businessProps(Business $business): array
    {
        return [
            'name' => $business->name,
            'slug' => $business->slug,
            'category' => Business::CATEGORIES[$business->category] ?? $business->category,
            'tagline' => $business->tagline,
            'address' => $business->address,
            'phone' => $business->phone,
            'timezone' => $business->timezone,
            'accent_color' => $business->accent_color,
            'deposit_percent' => $business->deposit_percent,
            'cancellation_notice_hours' => $business->cancellation_notice_hours,
            'hold_minutes' => $business->hold_minutes,
        ];
    }
}
