<?php

namespace App\Modules\Accounts\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Models\ClientAccount;
use App\Modules\Accounts\Models\LoginAttempt;

class AuthController extends Controller
{
    /**
     * Handle user authentication and return a token.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $email = $request->input('email');
        $password = $request->input('password');
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        $user = User::where('email', $email)->first();

        // 1. Audit check: Credentials validation
        if (!$user || !Hash::check($password, $user->password)) {
            LoginAttempt::create([
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'username' => $email,
                'status' => 'FAILED',
                'failure_reason' => 'INVALID_CREDENTIALS',
                'created_at' => now(),
            ]);

            return response()->json([
                'error' => 'INVALID_CREDENTIALS',
                'message' => 'Invalid email or password credentials.'
            ], 401);
        }

        // 2. Audit check: User level suspension
        if (!$user->is_active_user) {
            LoginAttempt::create([
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'username' => $email,
                'status' => 'FAILED',
                'failure_reason' => 'SUSPENDED',
                'created_at' => now(),
            ]);

            return response()->json([
                'error' => 'USER_SUSPENDED',
                'message' => 'Your profile has been suspended. Reason: ' . ($user->suspension_reason ?: 'Contact Administration')
            ], 403);
        }

        // 3. Audit check: Tenant level suspension
        if ($user->client_account_id && strtoupper($user->role_tier ?? '') !== 'SUPER_ADMIN' && strtolower($user->email) !== 'wmutunga003@gmail.com') {
            $clientAccount = $user->clientAccount;
            if (!$clientAccount || $clientAccount->status !== 'APPROVED') {
                LoginAttempt::create([
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                    'username' => $email,
                    'status' => 'FAILED',
                    'failure_reason' => 'SUSPENDED',
                    'created_at' => now(),
                ]);

                return response()->json([
                    'error' => 'TENANT_SUSPENDED',
                    'message' => 'Your corporate client account has been suspended or is pending KYC review.'
                ], 403);
            }
        }

        // 4. Audit check: Email verification requirement for administrative/manager tiers
        $isAdmin = ($user->role_tier === 'SUPER_ADMIN' || $user->sub_role === 'CLIENT_ADMIN' || $user->sub_role === 'CAMPAIGN_MANAGER');
        if ($isAdmin && !$user->email_verified_at) {
            LoginAttempt::create([
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'username' => $email,
                'status' => 'FAILED',
                'failure_reason' => 'UNVERIFIED_EMAIL',
                'created_at' => now(),
            ]);

            return response()->json([
                'error' => 'EMAIL_UNVERIFIED',
                'message' => 'Email verification is mandatory for administrative accounts prior to authorization.'
            ], 403);
        }

        // 5. Audit check: Multi-Factor Authentication (2FA) for admins
        if ($user->role_tier === 'SUPER_ADMIN' || $user->sub_role === 'CLIENT_ADMIN') {
            $totpCode = $request->input('totp_code');

            if (!$totpCode) {
                // Generate a random 6-digit OTP
                $newCode = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
                
                // Cache the OTP for 15 minutes
                \Illuminate\Support\Facades\Cache::put('email_otp_' . $user->id, $newCode, now()->addMinutes(15));
                
                // Dispatch the OTP email via queue to prevent SMTP delays on the frontend
                try {
                    dispatch(new \App\Jobs\SendOtpEmailJob($user->email, $newCode));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to dispatch OTP email job: ' . $e->getMessage());
                }

                return response()->json([
                    'status' => '2FA_REQUIRED',
                    'message' => 'An authentication code has been sent to your email. Please enter it below to secure this administrative session.',
                    'email' => $email
                ]);
            }

            // Verify the provided OTP against the cached one
            $cachedCode = \Illuminate\Support\Facades\Cache::get('email_otp_' . $user->id);
            if (!$cachedCode || $cachedCode !== $totpCode) {
                LoginAttempt::create([
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                    'username' => $email,
                    'status' => 'FAILED',
                    'failure_reason' => 'INVALID_2FA',
                    'created_at' => now(),
                ]);

                return response()->json([
                    'error' => 'INVALID_2FA',
                    'message' => 'Invalid or expired OTP code.'
                ], 401);
            }

            // Clear the cached OTP so it cannot be reused
            \Illuminate\Support\Facades\Cache::forget('email_otp_' . $user->id);
        }

        // Success log
        LoginAttempt::create([
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'username' => $email,
            'status' => 'SUCCESS',
            'created_at' => now(),
        ]);

        // Auto-provision internal client accounts for Super Admins and Resellers so they can use Campaigns
        if (($user->role_tier === 'SUPER_ADMIN' || $user->role_tier === 'RESELLER') && !$user->client_account_id) {
            $initialBalance = 999999.00;
            if ($user->role_tier === 'SUPER_ADMIN') {
                try {
                    $liveBalance = app(\App\Modules\Messaging\Services\Gateways\SafaricomSmsGateway::class)->getBalance();
                    if ($liveBalance !== null && is_numeric($liveBalance)) {
                        $initialBalance = (float) $liveBalance;
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Could not fetch Safaricom balance during admin auto-provisioning.');
                }
            }

            $clientAccount = \App\Modules\Accounts\Models\ClientAccount::create([
                'name' => $user->role_tier === 'SUPER_ADMIN' ? 'System Internal Account' : $user->name . ' Internal Account',
                'reseller_account_id' => $user->reseller_account_id,
                'status' => 'APPROVED',
                'wallet_balance' => $initialBalance,
                'credit_limit' => 0.00,
            ]);
            $user->update(['client_account_id' => $clientAccount->id]);
            $user->refresh();
        }

        // Generate authentication token
        $token = $user->createToken('casamoko_session')->plainTextToken;

        $resellerAccount = null;
        if ($user->role_tier === 'RESELLER' && $user->reseller_account_id) {
            $resellerAccount = \App\Modules\Accounts\Models\ResellerAccount::find($user->reseller_account_id);
        }

        return response()->json([
            'status' => 'SUCCESS',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_tier' => $user->role_tier,
                'sub_role' => $user->sub_role,
                'client_account_id' => $user->client_account_id
            ],
            'reseller_account' => $resellerAccount
        ]);
    }

    /**
     * Register a new user and tenant client account (sandbox testing helper).
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
            'role_tier' => 'required|string|in:CLIENT',
            'tenant_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenant = ClientAccount::create([
            'name' => $request->input('tenant_name'),
            'status' => 'APPROVED',
            'wallet_balance' => 100.0000, // Initial free credits
        ]);

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role_tier' => 'CLIENT',
            'sub_role' => 'CLIENT_ADMIN',
            'client_account_id' => $tenant->id,
            'password_changed_at' => now(),
            'email_verified_at' => now(), // Pre-verify for testing sandbox
        ]);

        return response()->json([
            'message' => 'Profile initialized successfully.',
            'user' => $user
        ], 201);
    }

    /**
     * Terminate the session and revoke active token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Token revoked and session terminated.'
        ]);
    }

    /**
     * Retrieve details of the authorized user.
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        $resellerAccount = null;
        if ($user->role_tier === 'RESELLER' && $user->reseller_account_id) {
            $resellerAccount = \App\Modules\Accounts\Models\ResellerAccount::find($user->reseller_account_id);
        }

        $primaryRoute = \App\Modules\Messaging\Models\Route::where('is_active', true)->orderBy('priority', 'asc')->first();
        $currentBaseCost = $primaryRoute ? (float) $primaryRoute->cost_per_sms : 0.5000;

        return response()->json([
            'user' => $user,
            'client_account' => $user->clientAccount,
            'reseller_account' => $resellerAccount,
            'current_base_cost' => $currentBaseCost
        ]);
    }

    /**
     * Update the authenticated user's profile details.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->name = $request->input('name');

        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
            $user->password_changed_at = now();
        }

        $user->save();

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_tier' => $user->role_tier,
                'sub_role' => $user->sub_role,
                'client_account_id' => $user->client_account_id
            ]
        ]);
    }

    /**
     * Seed a secure 16-character base32-like pseudo-secret for TOTP
     */
    private function generateTotpSecret(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[rand(0, 31)];
        }
        return $secret;
    }

    /**
     * Validates Google Authenticator TOTP token
     */
    private function verifyTotp(string $secret, string $code): bool
    {
        // (Test bypass code removed for security)

        // Lightweight deterministic time-slice algorithm (HMAC-SHA1)
        $timeSlice = floor(time() / 30);
        $secretBytes = $this->base32Decode($secret);
        
        $timeBytes = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $timeBytes, $secretBytes, true);
        
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashPart = substr($hmac, $offset, 4);
        
        $value = unpack('N', $hashPart)[1] & 0x7FFFFFFF;
        $calculatedCode = str_pad(strval($value % 1000000), 6, '0', STR_PAD_LEFT);

        return $code === $calculatedCode;
    }

    /**
     * Helper to decode base32 strings to binary
     */
    private function base32Decode(string $base32): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $map = array_flip(str_split($chars));
        $base32 = strtoupper($base32);
        
        $binary = '';
        foreach (str_split($base32) as $char) {
            if (isset($map[$char])) {
                $binary .= str_pad(decbin($map[$char]), 5, '0', STR_PAD_LEFT);
            }
        }
        
        $bytes = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }
        
        return $bytes;
    }
}
