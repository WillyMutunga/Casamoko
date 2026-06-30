<?php

namespace App\Modules\Accounts\Middlewares;

use Closure;
use Illuminate\Http\Request;

class TenantSuspensionMiddleware
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

        if ($user) {
            // 1. Verify user-level activation status
            if (!$user->is_active_user) {
                return response()->json([
                    'error' => 'USER_SUSPENDED',
                    'message' => 'Your user account has been suspended. Reason: ' . ($user->suspension_reason ?: 'No reason specified.')
                ], 403);
            }

            // 2. Verify tenant-level status
            if ($user->client_account_id && $user->role_tier !== 'SUPER_ADMIN') {
                $clientAccount = $user->clientAccount;

                if (!$clientAccount) {
                    return response()->json([
                        'error' => 'TENANT_NOT_FOUND',
                        'message' => 'No active tenant account could be found for this profile.'
                    ], 403);
                }

                if ($clientAccount->status !== 'APPROVED') {
                    return response()->json([
                        'error' => 'TENANT_SUSPENDED',
                        'message' => 'Your corporate client account has been suspended or is pending KYC review. Status: ' . $clientAccount->status
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
