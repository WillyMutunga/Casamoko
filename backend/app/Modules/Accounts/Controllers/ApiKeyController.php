<?php

namespace App\Modules\Accounts\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Accounts\Models\ApiKey;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $clientAccountId = $user->client_account_id ?: ($user->clientAccount ? $user->clientAccount->id : 1);
        $keys = ApiKey::where('client_account_id', $clientAccountId)->get();

        if ($keys->isEmpty()) {
            // Auto-generate initial active production API key for client
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
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $clientAccountId = $user->client_account_id ?: ($user->clientAccount ? $user->clientAccount->id : 1);

        // Generate a fresh live API key
        $rawKey = 'live_csmk_' . Str::random(24);

        // Delete old keys for simplicity so the client has 1 active live key
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
    }

    public function revoke(Request $request, $id)
    {
        $key = ApiKey::where('client_account_id', $request->user()->client_account_id)
                     ->where('id', $id)
                     ->firstOrFail();

        $key->delete();

        return response()->json(['status' => 'SUCCESS']);
    }
}
