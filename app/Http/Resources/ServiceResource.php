<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Service;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Service
 */
final class ServiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'duration_minutes' => $this->duration_minutes,
            'buffer_minutes' => $this->buffer_minutes,
            'price_cents' => $this->price_cents,
            'price' => Money::format($this->price_cents),
            'is_active' => $this->is_active,
            'staff_ids' => $this->whenLoaded('staff', fn () => $this->staff->map(fn (User $u): int => $u->id)->values()),
        ];
    }
}
