<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Payments\WebhookSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects any webhook whose HMAC does not match the *raw* body. The raw body
 * matters: re-encoding parsed JSON can reorder keys or change escaping and
 * break a perfectly valid signature.
 */
final class VerifyWebhookSignature
{
    public function __construct(private readonly WebhookSignature $signature) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->signature->verify($request->getContent(), $request->header(WebhookSignature::HEADER))) {
            Log::warning('Rejected webhook with an invalid signature.', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
