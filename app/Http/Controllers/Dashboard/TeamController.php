<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\TeamMemberRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkingHour;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class TeamController extends Controller
{
    public function index(TenantContext $tenant): Response
    {
        $this->authorize('manage-business');

        $members = $tenant->get()->members()
            ->with(['services:id', 'workingHours'])
            ->orderByRaw("role = 'owner' desc")
            ->orderBy('name')
            ->get();

        return Inertia::render('team/index', [
            'members' => $members->map(fn (User $m): array => [
                'id' => $m->id,
                'name' => $m->name,
                'title' => $m->title,
                'email' => $m->email,
                'role' => $m->role->value,
                'role_label' => $m->role->label(),
                'is_bookable' => $m->is_bookable,
                'service_ids' => $m->services->pluck('id'),
                'working_hours' => $m->workingHours->map(fn (WorkingHour $h): array => [
                    'weekday' => $h->weekday,
                    'starts_at' => substr($h->starts_at, 0, 5),
                    'ends_at' => substr($h->ends_at, 0, 5),
                ]),
            ]),
            'services' => ServiceResource::collection(Service::query()->orderBy('name')->get())->resolve(),
        ]);
    }

    public function store(TeamMemberRequest $request, TenantContext $tenant): RedirectResponse
    {
        DB::transaction(function () use ($request, $tenant): void {
            $member = User::query()->create([
                'business_id' => $tenant->id(),
                'role' => Role::Staff,
                'name' => $request->validated('name'),
                'title' => $request->validated('title'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'is_bookable' => $request->boolean('is_bookable', true),
            ]);
            $member->forceFill(['email_verified_at' => now()])->save();

            $this->syncSchedule($member, $request);
        });

        return back()->with('success', 'Profesional agregado al equipo.');
    }

    public function update(TeamMemberRequest $request, User $member, TenantContext $tenant): RedirectResponse
    {
        abort_unless($member->belongsToBusiness($tenant->id()), 404);

        DB::transaction(function () use ($request, $member): void {
            $member->fill($request->safe()->only(['name', 'title', 'email']));
            $member->is_bookable = $request->boolean('is_bookable', true);

            if ($request->filled('password')) {
                $member->password = (string) $request->validated('password');
            }

            $member->save();
            $this->syncSchedule($member, $request);
        });

        return back()->with('success', 'Cambios guardados.');
    }

    public function destroy(User $member, TenantContext $tenant): RedirectResponse
    {
        $this->authorize('manage-business');
        abort_unless($member->belongsToBusiness($tenant->id()), 404);

        if ($member->isOwner()) {
            return back()->with('error', 'No se puede eliminar al dueño/a del negocio.');
        }

        $hasUpcoming = $member->appointments()
            ->whereIn('status', AppointmentStatus::activeValues())
            ->where('starts_at', '>', now())
            ->exists();

        if ($hasUpcoming) {
            return back()->with('error', 'Tiene turnos próximos. Reasignalos o cancelalos antes de eliminarlo.');
        }

        $member->delete();

        return back()->with('success', 'Profesional eliminado.');
    }

    private function syncSchedule(User $member, TeamMemberRequest $request): void
    {
        $member->services()->sync(array_map('intval', (array) $request->validated('service_ids', [])));

        WorkingHour::query()->where('user_id', $member->id)->delete();

        foreach ($request->workingHours() as $row) {
            WorkingHour::query()->create([...$row, 'user_id' => $member->id]);
        }
    }
}
