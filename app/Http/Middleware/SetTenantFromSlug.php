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

        $business = match (true) {
            $param instanceof Business => $param,
            is_string($param) => Business::query()->where('slug', $param)->firstOrFail(),
            default => abort(404),
        };

        $route?->setParameter('business', $business);
        $this->tenant->set($business);

        return $next($request);
    }

    /**
     * Clear the tenant once the response is sent, so nothing that runs afterwards in
     * the same process (Octane, tests, terminable middleware) inherits it by accident.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->tenant->set(null);
    }
}
