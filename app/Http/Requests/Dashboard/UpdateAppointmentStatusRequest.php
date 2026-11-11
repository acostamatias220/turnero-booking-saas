<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by AppointmentPolicy in the controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                AppointmentStatus::Completed->value,
                AppointmentStatus::NoShow->value,
                AppointmentStatus::Cancelled->value,
            ])],
        ];
    }

    public function status(): AppointmentStatus
    {
        return AppointmentStatus::from((string) $this->validated('status'));
    }
}
