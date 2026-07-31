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
            return $user->clientAccount->id;
        }
        $first = ClientAccount::first();
        if ($first) {
            return $first->id;
        }
        $created = ClientAccount::create([
            'company_name' => 'Casamoko Default Account',
            'wallet_balance' => 1000.00
        ]);
        return $created->id;
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $clientAccountId = $this->resolveClientAccountId($user);
            $keys = ApiKey::where('client_account_id', $clientAccountId)->get();

            if ($keys->isEmpty()) {
                $rawKey = 'live_csmk_' . Str::random(24);
                $key = ApiKey::create([
                    'client_account_id' => $clientAccountId,
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

            ApiKey::where('client_account_id', $clientAccountId)->delete();

            $key = ApiKey::create([
                'client_account_id' => $clientAccountId,
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
            ApiKey::where('client_account_id', $clientAccountId)->where('id', $id)->delete();
            return response()->json(['status' => 'SUCCESS']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
