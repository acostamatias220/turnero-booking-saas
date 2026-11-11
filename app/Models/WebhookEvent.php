<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Inbox of provider events. The UNIQUE(provider, event_id) index is what makes
 * webhook delivery idempotent: providers retry, we process each event once.
 *
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property CarbonImmutable|null $processed_at
 * @property string|null $result
 */
class WebhookEvent extends Model
{
    protected $fillable = ['provider', 'event_id', 'type', 'payload', 'processed_at', 'result'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
