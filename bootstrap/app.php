<?php

declare(strict_types=1);

use App\Booking\InvalidTransitionException;
use App\Booking\SlotUnavailableException;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTenantFromSlug;
use App\Http\Middleware\SetTenantFromUser;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'tenant.user' => SetTenantFromUser::class,
            'tenant.slug' => SetTenantFromSlug::class,
            'webhook.signature' => VerifyWebhookSignature::class,
        ]);

        // The tenant must be known *before* route-model binding runs, so that
        // {appointment} / {service} are resolved through the tenant scope.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetTenantFromUser::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetTenantFromSlug::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (SlotUnavailableException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'code' => 'slot_unavailable'], Response::HTTP_CONFLICT);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });

        $exceptions->render(function (InvalidTransitionException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'code' => 'invalid_transition'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return back()->with('error', $e->getMessage());
        });
    })->create();
