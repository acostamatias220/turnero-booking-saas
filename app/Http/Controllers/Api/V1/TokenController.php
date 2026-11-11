<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Personal access tokens for the staff mobile app / integrations (Laravel Sanctum).
 */
final class TokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        // Same message and (roughly) same work for "no user" and "bad password".
        if ($user === null || ! Hash::check($data['password'], $user->password) || $user->business_id === null) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $abilities = $user->isOwner() ? ['appointments:read', 'appointments:write', 'services:read'] : ['appointments:read', 'appointments:write'];
        $token = $user->createToken($data['device_name'], $abilities, now()->addDays(30));

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601ZuluString(),
            'abilities' => $abilities,
        ], 201);
    }

    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()->delete();

        return response()->noContent();
    }
}
