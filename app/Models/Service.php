<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToBusiness;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $business_id
 * @property-read Business $business
 * @property string $name
 * @property string|null $description
 * @property int $duration_minutes
 * @property int $buffer_minutes
 * @property int $price_cents
 * @property bool $is_active
 */
class Service extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'duration_minutes', 'buffer_minutes', 'price_cents', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'price_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->where('is_bookable', true)->orderBy('name');
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** Minutes the professional is busy: the service itself plus clean-up/buffer time. */
    public function occupiedMinutes(): int
    {
        return $this->duration_minutes + $this->buffer_minutes;
    }

    /**
     * @param  Builder<Service>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
