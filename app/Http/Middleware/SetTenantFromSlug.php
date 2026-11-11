<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Business;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public booking page and public API: the tenant comes from `{business}` in the URL.
 */
final class SetTenantFromSlug
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $param = $route?->parameter('business');

        $business = $param instanceof Business
            ? $param
            : Business::query()->where('slug', (string) $param)->firstOrFail();

        $route?->setParameter('business', $business);
        $this->tenant->set($business);

        return $next($request);
    }
}
