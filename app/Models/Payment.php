<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Tenancy\BelongsToBusiness;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $business_id
 * @property int $appointment_id
 * @property string $provider
 * @property string $checkout_id
 * @property string|null $provider_payment_id
 * @property int $amount_cents
 * @property string $currency
 * @property PaymentStatus $status
 * @property CarbonImmutable|null $paid_at
 * @property-read Appointment $appointment
 */
class Payment extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'appointment_id', 'provider', 'checkout_id', 'provider_payment_id',
        'amount_cents', 'currency', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'paid_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
