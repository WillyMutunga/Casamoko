<?php

namespace App\Modules\Accounts\Middlewares;

use Closure;
use Illuminate\Http\Request;

class RequireAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || strtoupper($user->role_tier ?? '') !== 'SUPER_ADMIN') {
            return response()->json([
                'error' => 'ROLE_UNAUTHORIZED',
                'message' => 'This administrative endpoint is restricted to System Supervisor Accounts.'
            ], 403);
        }

        return $next($request);
    }
}
