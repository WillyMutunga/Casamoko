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

        // Validate the hashed token
        $hashedToken = hash('sha256', $token);
        
        $apiKey = ApiKey::where('api_key', $hashedToken)
                        ->where(function($q) {
                            $q->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                        })
                        ->with('clientAccount')
                        ->first();

        if (!$apiKey || !$apiKey->clientAccount) {
            Log::warning("Unauthorized API attempt with invalid key");
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Invalid or expired API Key'
            ], 401);
        }

        // Touch last used timestamp
        $apiKey->update(['last_used_at' => now()]);

        // Inject the client account into the request so the controller can use it
        $request->merge(['client_account_id' => $apiKey->client_account_id]);
        $request->attributes->set('clientAccount', $apiKey->clientAccount);

        return $next($request);
    }
}
