<?php

namespace App\Modules\Accounts\Middlewares;

use Closure;
use Illuminate\Http\Request;

class RequireResellerMiddleware
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

        if (!$user || !in_array($user->role_tier, ['RESELLER', 'SUPER_ADMIN'])) {
            return response()->json([
                'error' => 'ROLE_UNAUTHORIZED',
                'message' => 'This endpoint is restricted to Reseller Partners.'
            ], 403);
        }

        return $next($request);
    }
}
