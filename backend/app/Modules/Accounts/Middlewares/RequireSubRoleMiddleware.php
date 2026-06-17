<?php

namespace App\Modules\Accounts\Middlewares;

use Closure;
use Illuminate\Http\Request;

class RequireSubRoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string  ...$subRoles
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$subRoles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
                'message' => 'Authentication session required.'
            ], 401);
        }

        // Super admins have global bypass
        if ($user->role_tier === 'SUPER_ADMIN') {
            return $next($request);
        }

        // If user is client, check their sub-role
        if ($user->role_tier === 'CLIENT') {
            // CLIENT_ADMIN bypasses all sub-role checks for clients
            if ($user->sub_role === 'CLIENT_ADMIN') {
                return $next($request);
            }

            // Flatten and split any comma-separated strings (in case Laravel passes it as a single string with commas)
            $parsedSubRoles = [];
            foreach ($subRoles as $role) {
                $parsedSubRoles = array_merge($parsedSubRoles, explode(',', $role));
            }
            $subRoles = array_map('trim', $parsedSubRoles);

            if (in_array($user->sub_role, $subRoles)) {
                return $next($request);
            }
        }

        $parsedSubRoles = [];
        foreach ($subRoles as $role) {
            $parsedSubRoles = array_merge($parsedSubRoles, explode(',', $role));
        }
        $subRolesArray = array_map('trim', $parsedSubRoles);

        return response()->json([
            'error' => 'PRIVILEGE_UNAUTHORIZED',
            'message' => 'Insufficient sub-role privileges. Required role: ' . implode(' or ', $subRolesArray)
        ], 403);
    }
}
