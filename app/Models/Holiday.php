<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $business_id
 * @property-read Business $business
 * @property CarbonImmutable $date
 * @property string $name
 */
class Holiday extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'date', 'name'];

    protected function casts(): array
    {
        return ['date' => 'immutable_date'];
    }
}
