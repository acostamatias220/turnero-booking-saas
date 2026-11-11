<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Makes a model tenant-aware:
 *  - every query is filtered by the active business (fail-closed, see BusinessScope);
 *  - `business_id` is filled automatically from the active tenant;
 *  - a model can never be saved into a different tenant than the active one.
 *
 * @mixin Model
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope(new BusinessScope);

        // `saving` fires before `creating`, so fill and guard in a single listener.
        static::saving(function (Model $model): void {
            $context = app(TenantContext::class);

            if (! $context->check()) {
                return;
            }

            if ($model->getAttribute('business_id') === null) {
                $model->setAttribute('business_id', $context->id());
            }

            if ((int) $model->getAttribute('business_id') !== $context->id()) {
                throw new CrossTenantWriteException(sprintf(
                    'Refusing to save %s for business #%s while tenant #%s is active.',
                    $model::class,
                    (string) $model->getAttribute('business_id'),
                    (string) $context->id(),
                ));
            }
        });
    }

    /**
     * Explicit, greppable escape hatch for system-wide code (schedulers, webhooks).
     *
     * @return Builder<static>
     */
    public static function acrossTenants(): Builder
    {
        return static::query()->withoutGlobalScope(BusinessScope::class);
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
