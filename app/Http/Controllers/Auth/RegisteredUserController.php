<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Models\WorkingHour;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sign-up = onboarding: creates the business (tenant) and its owner in one step,
 * with a sensible default schedule so the booking page works immediately.
 */
class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/register', [
            'categories' => Business::CATEGORIES,
        ]);
    }

    public function store(Request $request, TenantContext $tenant): RedirectResponse
    {
        $request->validate([
            'business_name' => 'required|string|max:120',
            'category' => ['required', Rule::in(array_keys(Business::CATEGORIES))],
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($request, $tenant): User {
            $business = Business::query()->create([
                'name' => $request->string('business_name')->toString(),
                'slug' => $this->uniqueSlug($request->string('business_name')->toString()),
                'category' => $request->string('category')->toString(),
            ]);

            $user = User::query()->create([
                'business_id' => $business->id,
                'role' => Role::Owner,
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'password' => $request->string('password')->toString(),
            ]);

            $tenant->run($business, function () use ($user): void {
                foreach (range(1, 5) as $weekday) {
                    WorkingHour::query()->create(['user_id' => $user->id, 'weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '18:00']);
                }
            });

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'negocio';
        $slug = $base;

        for ($i = 2; Business::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
