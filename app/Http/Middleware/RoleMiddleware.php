<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Validate active status
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your user account is suspended or inactive'
            ], 403);
        }

        // Validate matching roles
        if (!in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have the required access permissions'
            ], 403);
        }

        return $next($request);
    }
}
