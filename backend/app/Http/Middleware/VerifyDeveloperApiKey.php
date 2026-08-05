<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Modules\Accounts\Models\ApiKey;
use Illuminate\Support\Facades\Log;

class VerifyDeveloperApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Missing or malformed Authorization header. Expected Bearer {API_KEY}'
            ], 401);
        }

        // Validate the raw token OR hashed token
        $hashedToken = hash('sha256', $token);
        
        $apiKey = ApiKey::where(function($q) use ($token, $hashedToken) {
                            $q->where('api_key', $token)
                              ->orWhere('api_key', $hashedToken);
                        })
                        ->where(function($q) {
                            $q->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                        })
                        ->with(['clientAccount', 'user.clientAccount'])
                        ->first();

        if (!$apiKey) {
            Log::warning("Unauthorized API attempt with invalid key: " . substr($token, 0, 12) . "...");
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Invalid or expired API Key'
            ], 401);
        }

        // Auto-heal key mapping: If owner user has a dedicated client_account_id, keep it synced!
        $clientAccount = $apiKey->clientAccount;
        if ($apiKey->user && !empty($apiKey->user->client_account_id) && $apiKey->client_account_id !== $apiKey->user->client_account_id) {
            $apiKey->update(['client_account_id' => $apiKey->user->client_account_id]);
            $clientAccount = \App\Modules\Accounts\Models\ClientAccount::find($apiKey->user->client_account_id);
        }

        if (!$clientAccount) {
            $clientAccount = \App\Modules\Accounts\Models\ClientAccount::firstOrCreate(
                ['id' => $apiKey->client_account_id],
                ['company_name' => 'API Account', 'wallet_balance' => 100.00]
            );
        }

        // Touch last used timestamp
        $apiKey->update(['last_used_at' => now()]);

        // Inject the client account into the request so the controller can use it
        $request->merge(['client_account_id' => $clientAccount->id]);
        $request->attributes->set('clientAccount', $clientAccount);

        return $next($request);
    }
}
