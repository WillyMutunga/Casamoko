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
        $keys = ApiKey::where('client_account_id', $request->user()->client_account_id)->get();
        return response()->json(['api_keys' => $keys]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        // Generate a cryptographically secure random key
        $rawKey = 'csmk_live_' . Str::random(40);

        $key = ApiKey::create([
            'client_account_id' => $request->user()->client_account_id,
            'name' => $request->name,
            'api_key' => hash('sha256', $rawKey), // In DB we store the hash
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'api_key' => $key,
            'raw_key' => $rawKey // Only show the raw key once!
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
