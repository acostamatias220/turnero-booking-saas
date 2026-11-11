<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\StaffResource;
use App\Models\Service;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class ServiceController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $this->authorize('viewAny', Service::class);

        return Inertia::render('services/index', [
            'services' => ServiceResource::collection(
                Service::query()->with('staff')->withCount('appointments')->orderByDesc('is_active')->orderBy('name')->get()
            )->resolve(),
            'staff' => StaffResource::collection($tenant->get()->staff()->get())->resolve(),
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $this->authorize('create', Service::class);

        DB::transaction(function () use ($request): void {
            $service = Service::query()->create($request->serviceAttributes());
            $service->staff()->sync($request->staffIds());
        });

        return back()->with('success', 'Servicio creado.');
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $this->authorize('update', $service);

        DB::transaction(function () use ($request, $service): void {
            $service->update($request->serviceAttributes());
            $service->staff()->sync($request->staffIds());
        });

        return back()->with('success', 'Servicio actualizado.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->authorize('delete', $service);

        if ($service->appointments()->exists()) {
            // Appointment history references it (FK RESTRICT): keep it for reporting, just hide it.
            $service->update(['is_active' => false]);

            return back()->with('success', 'El servicio tiene turnos históricos: lo desactivamos en lugar de borrarlo.');
        }

        $service->delete();

        return back()->with('success', 'Servicio eliminado.');
    }
}
