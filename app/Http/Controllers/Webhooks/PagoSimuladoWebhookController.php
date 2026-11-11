<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\WebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Receive → store → acknowledge fast; the actual work happens in a queued job.
 * Providers time out slow endpoints and retry, so we never do business logic inline.
 */
final class PagoSimuladoWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'max:191'],
            'type' => ['required', 'string', 'max:60'],
            'data' => ['required', 'array'],
            'data.checkout_id' => ['required', 'string'],
        ]);

        $now = CarbonImmutable::now();

        // INSERT IGNORE on UNIQUE(provider, event_id): exactly one delivery wins.
        $inserted = DB::table('webhook_events')->insertOrIgnore([
            'provider' => 'pagosimulado',
            'event_id' => $data['id'],
            'type' => $data['type'],
            'payload' => $request->getContent(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            return response()->json(['status' => 'duplicate']);
        }

        $event = WebhookEvent::query()->where('provider', 'pagosimulado')->where('event_id', $data['id'])->firstOrFail();
        ProcessPaymentWebhook::dispatch($event->id);

        return response()->json(['status' => 'accepted'], 202);
    }
}
