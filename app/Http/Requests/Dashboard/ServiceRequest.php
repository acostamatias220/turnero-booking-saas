<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ServicePolicy in the controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $businessId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            // Multiples of 5 keep every appointment aligned to the 5-minute block grid.
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480', 'multiple_of:5'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:120', 'multiple_of:5'],
            'price' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'is_active' => ['boolean'],
            'staff_ids' => ['array'],
            // Only members of *this* business can be assigned.
            'staff_ids.*' => ['integer', Rule::exists('users', 'id')->where('business_id', $businessId)],
        ];
    }

    /** @return array<string, mixed> */
    public function serviceAttributes(): array
    {
        return [
            'name' => $this->validated('name'),
            'description' => $this->validated('description'),
            'duration_minutes' => (int) $this->validated('duration_minutes'),
            'buffer_minutes' => (int) $this->validated('buffer_minutes'),
            'price_cents' => (int) round(((float) $this->validated('price')) * 100),
            'is_active' => $this->boolean('is_active', true),
        ];
    }

    /** @return list<int> */
    public function staffIds(): array
    {
        return array_values(array_map('intval', (array) $this->validated('staff_ids', [])));
    }
}
