<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-safe view of a professional (no e-mail).
 *
 * @mixin User
 */
final class StaffResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'initials' => collect(explode(' ', $this->name))->take(2)->map(fn (string $p): string => mb_substr($p, 0, 1))->implode(''),
        ];
    }
}
