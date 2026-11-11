<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

final class AvailabilityRequest extends FormRequest
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
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
