<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Tenancy\TenantContext;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class BusinessSettingsController extends Controller
{
    public function edit(TenantContext $tenant): Response
    {
        $this->authorize('manage-business');
        $business = $tenant->get();

        return Inertia::render('settings/business', [
            'business' => $business->only([
                'name', 'slug', 'category', 'tagline', 'phone', 'address', 'timezone', 'slot_interval_minutes',
                'min_notice_minutes', 'booking_horizon_days', 'cancellation_notice_hours', 'deposit_percent',
                'hold_minutes', 'accent_color',
            ]),
            'categories' => Business::CATEGORIES,
            'timezones' => DateTimeZone::listIdentifiers(DateTimeZone::AMERICA),
            'public_url' => route('public.booking', $business),
        ]);
    }

    public function update(Request $request, TenantContext $tenant): RedirectResponse
    {
        $this->authorize('manage-business');
        $business = $tenant->get();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:60', 'alpha_dash:ascii', 'lowercase', Rule::unique('businesses', 'slug')->ignore($business->id)],
            'category' => ['required', Rule::in(array_keys(Business::CATEGORIES))],
            'tagline' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:190'],
            'timezone' => ['required', 'timezone:all'],
            'slot_interval_minutes' => ['required', 'integer', Rule::in([5, 10, 15, 20, 30, 60])],
            'min_notice_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'booking_horizon_days' => ['required', 'integer', 'min:1', 'max:180'],
            'cancellation_notice_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'deposit_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'hold_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $business->update($data);

        return back()->with('success', 'Configuración guardada.');
    }
}
