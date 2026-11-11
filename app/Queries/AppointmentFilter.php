<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared filtering for the dashboard list and the API: owners see everyone,
 * professionals only their own agenda.
 */
final class AppointmentFilter
{
    /**
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    public function apply(Builder $query, Request $request, User $user): Builder
    {
        $tz = $user->business->timezone ?? 'UTC';

        return $query
            ->when(! $user->isOwner(), fn (Builder $q) => $q->where('staff_id', $user->id))
            ->when($user->isOwner() && $request->filled('staff_id'), fn (Builder $q) => $q->where('staff_id', $request->integer('staff_id')))
            ->when(
                $request->filled('status') && AppointmentStatus::tryFrom((string) $request->string('status')) !== null,
                fn (Builder $q) => $q->where('status', (string) $request->string('status')),
            )
            ->when($this->date($request, 'from', $tz), fn (Builder $q, CarbonImmutable $from) => $q->where('starts_at', '>=', $from->startOfDay()->utc()))
            ->when($this->date($request, 'to', $tz), fn (Builder $q, CarbonImmutable $to) => $q->where('starts_at', '<', $to->startOfDay()->addDay()->utc()))
            ->when($request->filled('q'), function (Builder $q) use ($request): void {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->string('q')).'%';
                $q->whereHas('customer', fn (Builder $c) => $c->where('name', 'like', $term)->orWhere('email', 'like', $term));
            });
    }

    private function date(Request $request, string $key, string $tz): ?CarbonImmutable
    {
        $value = (string) $request->string($key);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $value, $tz) ?: null;
    }
}
