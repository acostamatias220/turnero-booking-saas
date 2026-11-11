<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

final class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->business_id !== null;
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Service $service): bool
    {
        return $user->isOwner() && $user->belongsToBusiness($service->business_id);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->update($user, $service);
    }
}
