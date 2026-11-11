<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class TeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-business') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $member = $this->route('member');
        $memberId = $member instanceof User ? $member->id : null;
        $businessId = app(TenantContext::class)->id();
        $time = ['required', 'date_format:H:i', 'regex:/^\d{2}:[0-5][05]$/'];

        return [
            'name' => ['required', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($memberId)],
            'password' => $memberId === null ? ['required', Password::defaults()] : ['nullable', Password::defaults()],
            'is_bookable' => ['boolean'],
            'service_ids' => ['array'],
            'service_ids.*' => ['integer', Rule::exists('services', 'id')->where('business_id', $businessId)],
            'working_hours' => ['array', $this->noOverlaps(...)],
            'working_hours.*.weekday' => ['required', 'integer', 'between:1,7'],
            'working_hours.*.starts_at' => $time,
            'working_hours.*.ends_at' => [...$time, 'after:working_hours.*.starts_at'],
        ];
    }

    /** @param  Closure(string): void  $fail */
    private function noOverlaps(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        /** @var array<int, list<array{0: string, 1: string}>> $byDay */
        $byDay = [];

        foreach ($value as $row) {
            if (is_array($row) && isset($row['weekday'], $row['starts_at'], $row['ends_at'])) {
                $byDay[(int) $row['weekday']][] = [(string) $row['starts_at'], (string) $row['ends_at']];
            }
        }

        foreach ($byDay as $ranges) {
            sort($ranges);

            for ($i = 1, $n = count($ranges); $i < $n; $i++) {
                if ($ranges[$i][0] < $ranges[$i - 1][1]) {
                    $fail('Hay franjas horarias superpuestas en el mismo día.');

                    return;
                }
            }
        }
    }

    /** @return list<array{weekday: int, starts_at: string, ends_at: string}> */
    public function workingHours(): array
    {
        /** @var list<array{weekday: int|string, starts_at: string, ends_at: string}> $rows */
        $rows = (array) $this->validated('working_hours', []);

        return array_map(fn (array $r): array => [
            'weekday' => (int) $r['weekday'],
            'starts_at' => $r['starts_at'],
            'ends_at' => $r['ends_at'],
        ], $rows);
    }
}
