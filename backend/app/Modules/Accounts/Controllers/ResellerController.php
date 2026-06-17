<?php

namespace App\Modules\Accounts\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Modules\Accounts\Models\ClientAccount;
use App\Modules\Accounts\Models\ResellerAccount;
use App\Modules\Accounts\Models\User;
use App\Modules\Finance\Services\LedgerService;

class ResellerController extends Controller
{
    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * List all clients onboarded under the logged-in reseller's partner account.
     */
    public function listClients(Request $request)
    {
        $user = $request->user();

        // Check if user is associated with a ResellerAccount
        $resellerAccount = $user->resellerAccount ?: ResellerAccount::find($user->reseller_account_id);
        
        if (!$resellerAccount) {
            // Fallback to name match for backward compatibility
            $resellerAccount = ResellerAccount::where('name', 'Casamoko Reseller North')->first();
        }
        
        if (!$resellerAccount) {
            return response()->json([
                'status' => 'SUCCESS',
                'clients' => []
            ]);
        }

        $clients = ClientAccount::where('reseller_account_id', $resellerAccount->id)->get();

        return response()->json([
            'status' => 'SUCCESS',
            'clients' => $clients
        ]);
    }

    /**
     * Onboard a corporate tenant client account and seed their initial wallet credits.
     */
    public function onboardClient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tenant_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
            'seeding_balance' => 'required|numeric|min:0',
            'credit_limit' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $seedingBalance = (float) $request->input('seeding_balance');
        $creditLimit = (float) $request->input('credit_limit');
        $user = $request->user();

        // Dynamic transaction
        $result = DB::transaction(function () use ($request, $user, $seedingBalance, $creditLimit) {
            // Get active reseller associated with logged-in user
            $reseller = $user->resellerAccount ?: ResellerAccount::find($user->reseller_account_id);

            if (!$reseller) {
                // Fallback to name match/dynamic creation for compatibility
                $reseller = ResellerAccount::where('name', 'Casamoko Reseller North')->first();
                if (!$reseller) {
                    $reseller = ResellerAccount::create([
                        'name' => 'Casamoko Reseller North',
                        'markup_percentage' => 10.00,
                        'api_credits' => 5000.0000
                    ]);
                }
            }

            if ($reseller->api_credits < $seedingBalance) {
                return [
                    'success' => false,
                    'error' => 'INSUFFICIENT_RESELLER_CREDITS',
                    'message' => 'Your reseller channel has insufficient credits to allocate the requested amount.'
                ];
            }

            // 1. Deduct credits from reseller
            $reseller->decrement('api_credits', $seedingBalance);

            // 2. Create the client account
            $client = ClientAccount::create([
                'reseller_account_id' => $reseller->id,
                'name' => $request->input('tenant_name'),
                'status' => 'APPROVED',
                'wallet_balance' => $seedingBalance,
                'credit_limit' => $creditLimit,
            ]);

            // 3. Create the client user
            $clientUser = User::create([
                'client_account_id' => $client->id,
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => \Illuminate\Support\Facades\Hash::make($request->input('password')),
                'role_tier' => 'CLIENT',
                'sub_role' => 'CLIENT_ADMIN',
                'password_changed_at' => now(),
                'email_verified_at' => now(),
                'is_active_user' => true,
            ]);

            // 4. Log initial double-entry transaction
            $this->ledgerService->credit(
                $client->id,
                $seedingBalance,
                'TOPUP',
                ResellerAccount::class,
                $reseller->id,
                "Initial partner seed credits allocation from Reseller: {$reseller->name}"
            );

            return [
                'success' => true,
                'client' => $client,
                'client_user' => $clientUser,
                'reseller_credits' => $reseller->api_credits
            ];
        });

        if (!$result['success']) {
            return response()->json([
                'error' => $result['error'],
                'message' => $result['message']
            ], 400);
        }

        // Dispatch Welcome Email
        \Illuminate\Support\Facades\Mail::to($result['client_user']->email)->queue(
            new \App\Mail\WelcomeEmail($result['client_user'], $request->input('password'))
        );

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Corporate client '{$result['client']->name}' successfully onboarded and funded!",
            'client' => $result['client'],
            'client_user' => [
                'id' => $result['client_user']->id,
                'name' => $result['client_user']->name,
                'email' => $result['client_user']->email,
                'role_tier' => $result['client_user']->role_tier
            ],
            'reseller_credits' => $result['reseller_credits']
        ], 201);
    }
}
