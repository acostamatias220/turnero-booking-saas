<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

/**
 * Defence in depth: the tenant scope already hides other businesses' rows,
 * the policy additionally restricts professionals to their own agenda.
 */
final class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->business_id !== null;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->belongsToBusiness($appointment->business_id)
            && ($user->isOwner() || $appointment->staff_id === $user->id);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->view($user, $appointment);
    }
}
