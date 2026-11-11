<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToBusiness;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

/**
 * End customer of a business. The same e-mail in two businesses is two customers:
 * tenants never share personal data.
 *
 * @property int $id
 * @property int $business_id
 * @property-read Business $business
 * @property string $name
 * @property string $email
 * @property string|null $phone
 */
class Customer extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use Notifiable;

    protected $fillable = ['name', 'email', 'phone'];

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
