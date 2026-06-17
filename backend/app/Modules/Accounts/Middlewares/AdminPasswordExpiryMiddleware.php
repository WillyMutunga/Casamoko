<?php

namespace App\Modules\Accounts\Middlewares;

use Closure;
use Illuminate\Http\Request;

class AdminPasswordExpiryMiddleware
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

        if ($user && $user->isSuperAdmin()) {
            $passwordChangedAt = $user->password_changed_at;

            // If never set or set longer than 90 days ago, enforce expiry block
            if (!$passwordChangedAt || $passwordChangedAt->addDays(90)->isPast()) {
                return response()->json([
                    'error' => 'PASSWORD_EXPIRED',
                    'message' => 'Administrative policy requires a password rotation every 90 days. Your password has expired. Please reset it immediately.'
                ], 403);
            }
        }

        return $next($request);
    }
}
