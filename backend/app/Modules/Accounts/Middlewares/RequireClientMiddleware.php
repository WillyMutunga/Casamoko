<?php

namespace App\Modules\Accounts\Middlewares;

use Closure;
use Illuminate\Http\Request;

class RequireClientMiddleware
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

        if (!$user || $user->role_tier !== 'CLIENT') {
            return response()->json([
                'error' => 'ROLE_UNAUTHORIZED',
                'message' => 'This endpoint is restricted to Client Accounts.'
            ], 403);
        }

        return $next($request);
    }
}
