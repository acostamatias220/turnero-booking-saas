<?php

declare(strict_types=1);

use App\Http\Controllers\Dashboard\AgendaController;
use App\Http\Controllers\Dashboard\AppointmentController;
use App\Http\Controllers\Dashboard\BusinessSettingsController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\ServiceController;
use App\Http\Controllers\Dashboard\TeamController;
use App\Http\Controllers\Dashboard\TimeOffController;
use App\Http\Controllers\Public\AppointmentStatusController;
use App\Http\Controllers\Public\BookingPageController;
use App\Http\Controllers\Public\PagoSimuladoController;
use App\Models\Business;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('welcome', [
    'demo' => Business::query()->orderBy('id')->limit(3)->get(['name', 'slug', 'category', 'tagline', 'accent_color']),
]))->name('home');

/*
| Public booking site, one per business: /b/{slug}
*/
Route::prefix('b/{business}')->middleware('tenant.slug')->name('public.')->group(function () {
    Route::get('/', [BookingPageController::class, 'show'])->name('booking');
    Route::post('/turnos', [BookingPageController::class, 'store'])->middleware('throttle:bookings')->name('appointments.store');
    Route::get('/turnos/{appointment}', [AppointmentStatusController::class, 'show'])->name('appointments.show');
    Route::get('/turnos/{appointment}/calendario.ics', [AppointmentStatusController::class, 'calendar'])->name('appointments.calendar');
    Route::get('/turnos/{appointment}/cancelar', [AppointmentStatusController::class, 'confirmCancel'])->middleware('signed')->name('appointments.cancel');
    Route::post('/turnos/{appointment}/cancelar', [AppointmentStatusController::class, 'cancel'])->middleware('signed')->name('appointments.cancel.store');
});

/*
| PagoSimulado: sandbox payment provider's hosted checkout (would live on the provider's domain).
*/
Route::get('pagosimulado/checkout/{checkoutId}', [PagoSimuladoController::class, 'show'])->name('pagosimulado.checkout');
Route::post('pagosimulado/checkout/{checkoutId}', [PagoSimuladoController::class, 'complete'])->middleware('throttle:20,1')->name('pagosimulado.complete');

/*
| Back-office for owners and professionals.
*/
Route::middleware(['auth', 'tenant.user'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('agenda', AgendaController::class)->name('agenda');

    Route::get('turnos', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::patch('turnos/{appointment}/estado', [AppointmentController::class, 'updateStatus'])->name('appointments.status');

    Route::get('servicios', [ServiceController::class, 'index'])->name('services.index');
    Route::post('servicios', [ServiceController::class, 'store'])->name('services.store');
    Route::put('servicios/{service}', [ServiceController::class, 'update'])->name('services.update');
    Route::delete('servicios/{service}', [ServiceController::class, 'destroy'])->name('services.destroy');

    Route::middleware('can:manage-business')->group(function () {
        Route::get('equipo', [TeamController::class, 'index'])->name('team.index');
        Route::post('equipo', [TeamController::class, 'store'])->name('team.store');
        Route::put('equipo/{member}', [TeamController::class, 'update'])->name('team.update');
        Route::delete('equipo/{member}', [TeamController::class, 'destroy'])->name('team.destroy');

        Route::get('ausencias', [TimeOffController::class, 'index'])->name('time-off.index');
        Route::post('ausencias', [TimeOffController::class, 'store'])->name('time-off.store');
        Route::delete('ausencias/{timeOff}', [TimeOffController::class, 'destroy'])->name('time-off.destroy');
        Route::post('feriados', [TimeOffController::class, 'storeHoliday'])->name('holidays.store');
        Route::delete('feriados/{holiday}', [TimeOffController::class, 'destroyHoliday'])->name('holidays.destroy');

        Route::get('settings/business', [BusinessSettingsController::class, 'edit'])->name('business.edit');
        Route::patch('settings/business', [BusinessSettingsController::class, 'update'])->name('business.update');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
