<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Payments\WebhookSignature;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Plays the role of the payment provider's servers: POSTs a signed event to our
 * webhook endpoint over real HTTP, retrying with backoff like real providers do.
 */
final class DeliverSimulatedWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [2, 10, 60, 300];

    /**
     * @param  array<string, mixed>  $event
     */
    public function __construct(public readonly array $event) {}

    public function handle(WebhookSignature $signature): void
    {
        $body = (string) json_encode($this->event, JSON_THROW_ON_ERROR);

        Http::withHeaders([WebhookSignature::HEADER => $signature->sign($body)])
            ->withBody($body, 'application/json')
            ->timeout(10)
            ->post(route('webhooks.pagosimulado'))
            ->throw();
    }
}
