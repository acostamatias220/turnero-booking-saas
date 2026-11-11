<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One 5-minute unit of a professional's time owned by an active appointment.
 * The (staff_id, starts_at) primary key is the database-level double-booking guard.
 *
 * @property int $staff_id
 * @property CarbonImmutable $starts_at
 * @property int $appointment_id
 */
class AppointmentBlock extends Model
{
    public const MINUTES = 5;

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'appointment_id';

    protected $fillable = ['staff_id', 'starts_at', 'appointment_id'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime'];
    }
}
