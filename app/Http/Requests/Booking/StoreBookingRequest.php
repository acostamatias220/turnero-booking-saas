<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Booking\BookingRequest;
use Illuminate\Foundation\Http\FormRequest;

final class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date', 'after:now'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +()\-]{6,}$/'],
            'notes' => ['nullable', 'string', 'max:500'],
            // Honeypot: real users never see this field.
            'website' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'service_id' => 'servicio',
            'staff_id' => 'profesional',
            'starts_at' => 'horario',
            'name' => 'nombre',
            'phone' => 'teléfono',
            'notes' => 'comentarios',
        ];
    }

    public function toBookingRequest(): BookingRequest
    {
        /** @var array{service_id: int|string, starts_at: string, staff_id?: int|string|null, name: string, email: string, phone?: string|null, notes?: string|null} $data */
        $data = $this->validated();

        return BookingRequest::fromArray($data);
    }
}
