<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Appointment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 */
final class AppointmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $tz = $this->business->timezone;

        return [
            'id' => $this->public_id,
            'reference' => $this->reference(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'starts_at' => $this->starts_at->toIso8601ZuluString(),
            'ends_at' => $this->ends_at->toIso8601ZuluString(),
            'local_date' => $this->starts_at->setTimezone($tz)->toDateString(),
            'local_start' => $this->starts_at->setTimezone($tz)->format('H:i'),
            'local_end' => $this->ends_at->setTimezone($tz)->format('H:i'),
            'duration_minutes' => (int) $this->starts_at->diffInMinutes($this->ends_at),
            'price_cents' => $this->price_cents,
            'price' => Money::format($this->price_cents),
            'deposit_cents' => $this->deposit_cents,
            'deposit' => Money::format($this->deposit_cents),
            'hold_expires_at' => $this->hold_expires_at?->toIso8601ZuluString(),
            'notes' => $this->notes,
            'service' => $this->whenLoaded('service', fn () => ['id' => $this->service->id, 'name' => $this->service->name]),
            'staff' => $this->whenLoaded('staff', fn () => ['id' => $this->staff->id, 'name' => $this->staff->name]),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ]),
            'payment' => $this->whenLoaded('payment', fn () => $this->payment === null ? null : [
                'status' => $this->payment->status->value,
                'status_label' => $this->payment->status->label(),
                'amount' => Money::format($this->payment->amount_cents),
            ]),
        ];
    }
}
