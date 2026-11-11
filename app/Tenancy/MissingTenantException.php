<?php

declare(strict_types=1);

namespace App\Tenancy;

use LogicException;

/**
 * Thrown when a tenant-scoped model is queried without an active tenant.
 *
 * Failing loudly is deliberate: silently returning every tenant's rows (or none)
 * would turn a missing middleware into a data leak or a confusing empty screen.
 */
final class MissingTenantException extends LogicException
{
    public static function forModel(string $model): self
    {
        return new self("No tenant is active while querying [{$model}]. Wrap the code in TenantContext::run() or use {$model}::acrossTenants() explicitly.");
    }
}
