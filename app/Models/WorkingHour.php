<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToBusiness;
use Database\Factories\WorkingHourFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A wall-clock range in the business timezone. Several rows on the same weekday
 * model breaks (e.g. 09:00–13:00 and 14:00–19:00).
 *
 * @property int $id
 * @property int $business_id
 * @property int $user_id
 * @property int $weekday
 * @property string $starts_at
 * @property string $ends_at
 */
class WorkingHour extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<WorkingHourFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'weekday', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
