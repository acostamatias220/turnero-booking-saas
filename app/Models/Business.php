<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use Carbon\CarbonImmutable;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The tenant. Not scoped itself: it is resolved *before* a tenant exists
 * (public booking page by slug, the authenticated user's business).
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $category
 * @property string|null $tagline
 * @property string|null $phone
 * @property string|null $address
 * @property string $timezone
 * @property string $currency
 * @property int $slot_interval_minutes
 * @property int $min_notice_minutes
 * @property int $booking_horizon_days
 * @property int $cancellation_notice_hours
 * @property int $deposit_percent
 * @property int $hold_minutes
 * @property string $accent_color
 */
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    public const CATEGORIES = [
        'barbershop' => 'Barbería / Peluquería',
        'health' => 'Salud / Consultorio',
        'fitness' => 'Fitness / Estudio',
        'beauty' => 'Estética / Uñas',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'name', 'slug', 'category', 'tagline', 'phone', 'address', 'timezone', 'currency',
        'slot_interval_minutes', 'min_notice_minutes', 'booking_horizon_days',
        'cancellation_notice_hours', 'deposit_percent', 'hold_minutes', 'accent_color',
    ];

    protected function casts(): array
    {
        return [
            'slot_interval_minutes' => 'integer',
            'min_notice_minutes' => 'integer',
            'booking_horizon_days' => 'integer',
            'cancellation_notice_hours' => 'integer',
            'deposit_percent' => 'integer',
            'hold_minutes' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<User, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<User, $this> */
    public function staff(): HasMany
    {
        return $this->members()->where('is_bookable', true)->orderBy('name');
    }

    /** @return HasMany<User, $this> */
    public function owners(): HasMany
    {
        return $this->members()->where('role', Role::Owner->value);
    }

    public function requiresDeposit(): bool
    {
        return $this->deposit_percent > 0;
    }

    public function depositFor(int $priceCents): int
    {
        return (int) round($priceCents * $this->deposit_percent / 100);
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone);
    }

    public function localDate(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $this->timezone)->startOfDay();
    }
}
