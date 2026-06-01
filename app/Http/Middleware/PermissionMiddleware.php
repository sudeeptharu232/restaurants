<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Role-to-permissions structural mappings.
     */
    protected array $permissions = [
        'super_admin' => ['*'],
        'owner' => ['*'],
        'manager' => [
            'view_pos', 'manage_pos',
            'view_inventory', 'manage_inventory',
            'view_customers', 'manage_customers',
            'view_products', 'manage_products',
            'view_menu', 'manage_menu',
            'view_tables', 'manage_tables'
        ],
        'staff' => [
            'view_pos', 'manage_pos',
            'view_customers',
            'view_products',
            'view_menu',
            'view_tables'
        ],
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your user account is suspended or inactive'
            ], 403);
        }

        $userRole = $user->role ?? 'staff';
        $userPerms = $this->permissions[$userRole] ?? [];

        // Check wildcard or specific permission matches
        if (in_array('*', $userPerms) || in_array($permission, $userPerms)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden: You do not have permission to execute this operation'
        ], 403);
    }
}
