<?php

namespace App\Modules\Accounts\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Accounts\Models\ApiKey;
use App\Modules\Accounts\Models\ClientAccount;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ApiKeyController extends Controller
{
    private function resolveClientAccountId($user)
    {
        if (!empty($user->client_account_id) && ClientAccount::where('id', $user->client_account_id)->exists()) {
            return $user->client_account_id;
        }
        if (!empty($user->clientAccount) && ClientAccount::where('id', $user->clientAccount->id)->exists()) {
            $user->update(['client_account_id' => $user->clientAccount->id]);
            return $user->clientAccount->id;
        }
        
        $created = ClientAccount::create([
            'company_name' => ($user->name ?? 'User') . ' Account',
            'wallet_balance' => 100.0000
        ]);
        
        $user->update(['client_account_id' => $created->id]);
        return $created->id;
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $clientAccountId = $this->resolveClientAccountId($user);

            // Fetch keys by client_account_id or user_id for auto-healing
            $keys = ApiKey::where('client_account_id', $clientAccountId)
                          ->orWhere('user_id', $user->id)
                          ->get();

            // Auto-heal any keys pointing to legacy account
            foreach ($keys as $k) {
                if ($k->client_account_id !== $clientAccountId || $k->user_id !== $user->id) {
                    $k->update([
                        'client_account_id' => $clientAccountId,
                        'user_id' => $user->id
                    ]);
                }
            }

            if ($keys->isEmpty()) {
                $rawKey = 'live_csmk_' . Str::random(24);
                $key = ApiKey::create([
                    'client_account_id' => $clientAccountId,
                    'user_id' => $user->id,
                    'name' => 'Live Production Key',
                    'api_key' => $rawKey,
                ]);
                $keys = collect([$key]);
            }

            return response()->json([
                'status' => 'SUCCESS',
                'api_keys' => $keys,
                'active_key' => $keys->first()->api_key
            ]);
        } catch (\Exception $e) {
            Log::error("ApiKeyController index error: " . $e->getMessage());
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();
            $clientAccountId = $this->resolveClientAccountId($user);
            $rawKey = 'live_csmk_' . Str::random(24);

            ApiKey::where('client_account_id', $clientAccountId)
                  ->orWhere('user_id', $user->id)
                  ->delete();

            $key = ApiKey::create([
                'client_account_id' => $clientAccountId,
                'user_id' => $user->id,
                'name' => $request->name ?? 'Live Production Key',
                'api_key' => $rawKey,
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'api_key' => $key,
                'raw_key' => $rawKey,
                'active_key' => $rawKey
            ]);
        } catch (\Exception $e) {
            Log::error("ApiKeyController store error: " . $e->getMessage());
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }

    public function revoke(Request $request, $id)
    {
        try {
            $user = $request->user();
            $clientAccountId = $this->resolveClientAccountId($user);
            ApiKey::where('id', $id)
                  ->where(function($q) use ($clientAccountId, $user) {
                      $q->where('client_account_id', $clientAccountId)
                        ->orWhere('user_id', $user->id);
                  })
                  ->delete();
            return response()->json(['status' => 'SUCCESS']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
