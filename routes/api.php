<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PublicBusinessController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Webhooks\PagoSimuladoWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/pagosimulado', PagoSimuladoWebhookController::class)
    ->middleware(['throttle:webhooks', 'webhook.signature'])
    ->name('webhooks.pagosimulado');

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public booking API (what the booking page itself consumes).
    Route::prefix('public/businesses/{business}')->middleware('tenant.slug')->name('public.')->group(function () {
        Route::get('/', [PublicBusinessController::class, 'show'])->name('show');
        Route::get('availability', [PublicBusinessController::class, 'availability'])->middleware('throttle:availability')->name('availability');
        Route::get('available-dates', [PublicBusinessController::class, 'dates'])->middleware('throttle:availability')->name('dates');
        Route::post('appointments', [PublicBusinessController::class, 'book'])->middleware('throttle:bookings')->name('book');
    });

    Route::post('auth/token', [TokenController::class, 'store'])->middleware('throttle:api-token')->name('token.store');

    Route::middleware(['auth:sanctum', 'tenant.user', 'throttle:api'])->group(function () {
        Route::delete('auth/token', [TokenController::class, 'destroy'])->name('token.destroy');
        Route::get('me', MeController::class)->name('me');
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
        Route::patch('appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('appointments.status');
    });
});
