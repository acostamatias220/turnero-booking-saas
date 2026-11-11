<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard and staff API: the tenant is the authenticated member's business.
 * Runs before SubstituteBindings (see bootstrap/app.php) so route-model binding
 * is already tenant-scoped and a foreign ID simply 404s.
 */
final class SetTenantFromUser
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->business !== null, Response::HTTP_FORBIDDEN, 'Tu usuario no pertenece a ningún negocio.');

        $this->tenant->set($user->business);

        return $next($request);
    }
}
