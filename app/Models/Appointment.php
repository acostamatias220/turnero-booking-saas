<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Tenancy\BelongsToBusiness;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $public_id
 * @property int $business_id
 * @property int $staff_id
 * @property int $service_id
 * @property int $customer_id
 * @property AppointmentStatus $status
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable $blocked_until
 * @property int $price_cents
 * @property int $deposit_cents
 * @property CarbonImmutable|null $hold_expires_at
 * @property string|null $notes
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property CarbonImmutable|null $reminder_sent_at
 * @property-read Business $business
 * @property-read User $staff
 * @property-read Service $service
 * @property-read Customer $customer
 * @property-read Payment|null $payment
 */
class Appointment extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'staff_id', 'service_id', 'customer_id', 'status', 'starts_at', 'ends_at', 'blocked_until',
        'price_cents', 'deposit_cents', 'hold_expires_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'blocked_until' => 'immutable_datetime',
            'hold_expires_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'reminder_sent_at' => 'immutable_datetime',
            'price_cents' => 'integer',
            'deposit_cents' => 'integer',
        ];
    }

    /** ULIDs live in `public_id`; the integer PK stays internal. */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /** @return HasMany<AppointmentBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(AppointmentBlock::class);
    }

    /**
     * Appointments that currently occupy time: confirmed, or awaiting a deposit
     * whose hold has not expired yet.
     *
     * @param  Builder<Appointment>  $query
     */
    public function scopeOccupying(Builder $query, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();

        $query->where(function (Builder $q) use ($now): void {
            $q->where('status', AppointmentStatus::Confirmed->value)
                ->orWhere(function (Builder $q) use ($now): void {
                    $q->where('status', AppointmentStatus::PendingPayment->value)
                        ->where('hold_expires_at', '>', $now);
                });
        });
    }

    /**
     * @param  Builder<Appointment>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query->where('starts_at', '<', $to)->where('blocked_until', '>', $from);
    }

    public function localStart(): CarbonImmutable
    {
        return $this->starts_at->setTimezone($this->business->timezone);
    }

    public function localEnd(): CarbonImmutable
    {
        return $this->ends_at->setTimezone($this->business->timezone);
    }

    /** Last moment the customer may cancel by themselves. */
    public function cancellableUntil(): CarbonImmutable
    {
        return $this->starts_at->subHours($this->business->cancellation_notice_hours);
    }

    public function canBeCancelledByCustomer(?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        return $this->status->canTransitionTo(AppointmentStatus::Cancelled)
            && $now->lessThanOrEqualTo($this->cancellableUntil());
    }

    public function reference(): string
    {
        return strtoupper(substr($this->public_id, -8));
    }
}
