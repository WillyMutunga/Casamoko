<?php

namespace App\Modules\Accounts\Middlewares;

use Closure;
use Illuminate\Http\Request;
use App\Modules\Accounts\Models\LoginAttempt;

class BruteForceLimiterMiddleware
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
        $ip = $request->ip();
        $username = $request->input('email') ?: $request->input('username') ?: '';

        if (!empty($username)) {
            // Count failed attempts in the last 15 minutes for this IP or username
            $failedAttemptsCount = LoginAttempt::where(function ($query) use ($ip, $username) {
                $query->where('ip_address', $ip)
                      ->orWhere('username', $username);
            })
            ->where('status', 'FAILED')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

            if ($failedAttemptsCount >= 5) {
                // Log the blocked attempt as BRUTE_FORCE
                LoginAttempt::create([
                    'ip_address' => $ip,
                    'user_agent' => $request->userAgent(),
                    'username' => $username,
                    'status' => 'FAILED',
                    'failure_reason' => 'BRUTE_FORCE',
                    'created_at' => now(),
                ]);

                return response()->json([
                    'error' => 'BRUTE_FORCE',
                    'message' => 'Too many login attempts. Your account or IP is temporarily blocked due to security reasons. Please try again after 15 minutes.'
                ], 429);
            }
        }

        return $next($request);
    }
}
