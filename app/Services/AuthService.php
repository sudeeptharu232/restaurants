<?php

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Authenticate user credentials, switching database contexts dynamically if scoped to a tenant.
     */
    public function authenticate(array $credentials, ?string $tenantId = null): array
    {
        $email = $credentials['email'];
        $password = $credentials['password'];

        // Enforce basic login rate limiting (5 attempts per minute)
        $throttleKey = strtolower($email) . '|' . request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in {$seconds} seconds."]
            ]);
        }

        $user = null;
        $tenant = null;

        if ($tenantId) {
            // Find tenant and initialize connection dynamically
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                RateLimiter::hit($throttleKey);
                throw ValidationException::withMessages([
                    'tenant' => ['Business account not found']
                ]);
            }

            // Initialize context
            tenancy()->initialize($tenant);

            // Query isolated users
            $user = User::where('email', $email)->first();
        } else {
            // Query central users registry (Super Admin check)
            $user = User::where('email', $email)->first();
        }

        if (!$user || !Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey);
            throw ValidationException::withMessages([
                'email' => ['Invalid login credentials provided']
            ]);
        }

        // Validate active state
        if (!$user->is_active) {
            RateLimiter::hit($throttleKey);
            throw ValidationException::withMessages([
                'email' => ['Your user account is suspended or inactive']
            ]);
        }

        // Clear rate limiter upon successful login
        RateLimiter::clear($throttleKey);

        // Generate Sanctum access token
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'tenant' => $tenant
        ];
    }

    /**
     * Revoke current token on active user request.
     */
    public function logout($user): void
    {
        if ($user) {
            $user->currentAccessToken()->delete();
        }
    }
}
