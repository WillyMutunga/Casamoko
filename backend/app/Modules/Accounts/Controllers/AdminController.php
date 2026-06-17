<?php

namespace App\Modules\Accounts\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Modules\Accounts\Models\ResellerAccount;
use App\Modules\Accounts\Models\LoginAttempt;
use App\Modules\Messaging\Models\SenderID;

class AdminController extends Controller
{
    /**
     * Get list of active reseller channels.
     */
    public function listResellers()
    {
        $resellers = ResellerAccount::all()->map(function ($reseller) {
            $user = \App\Modules\Accounts\Models\User::where('reseller_account_id', $reseller->id)->first();
            $reseller->email = $user ? $user->email : null;
            return $reseller;
        });

        return response()->json([
            'status' => 'SUCCESS',
            'resellers' => $resellers
        ]);
    }

    /**
     * Get list of all corporate client accounts (FR-UAM-007).
     */
    public function listClients()
    {
        $clients = \App\Modules\Accounts\Models\ClientAccount::all();
        return response()->json([
            'status' => 'SUCCESS',
            'clients' => $clients
        ]);
    }


    /**
     * Create a new reseller channel.
     */
    public function createReseller(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
            'markup_percentage' => 'required|numeric|min:0|max:100',
            'api_credits' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $reseller = ResellerAccount::create([
                'name' => $request->input('name'),
                'markup_percentage' => $request->input('markup_percentage'),
                'api_credits' => $request->input('api_credits'),
            ]);

            $user = \App\Modules\Accounts\Models\User::create([
                'name' => $request->input('name') . ' Partner',
                'email' => $request->input('email'),
                'password' => \Illuminate\Support\Facades\Hash::make($request->input('password')),
                'role_tier' => 'RESELLER',
                'reseller_account_id' => $reseller->id,
                'password_changed_at' => now(),
                'email_verified_at' => now(),
                'is_active_user' => true,
            ]);

            return [
                'reseller' => $reseller,
                'user' => $user
            ];
        });

        // Dispatch Welcome Email
        \Illuminate\Support\Facades\Mail::to($result['user']->email)->queue(
            new \App\Mail\WelcomeEmail($result['user'], $request->input('password'))
        );

        $resellerWithEmail = $result['reseller'];
        $resellerWithEmail->email = $result['user']->email;

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Reseller channel account and manager profile registered successfully!',
            'reseller' => $resellerWithEmail,
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'role_tier' => $result['user']->role_tier
            ]
        ], 201);
    }

    /**
     * Update an existing reseller channel.
     */
    public function updateReseller(Request $request, $id)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'markup_percentage' => 'required|numeric|min:0|max:100',
            'api_credits' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reseller = ResellerAccount::find($id);
        if (!$reseller) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Reseller not found.'], 404);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $reseller) {
            $reseller->update([
                'name' => $request->input('name'),
                'markup_percentage' => $request->input('markup_percentage'),
                'api_credits' => $request->input('api_credits'),
            ]);

            $user = \App\Modules\Accounts\Models\User::where('reseller_account_id', $reseller->id)->first();
            if ($user) {
                $user->update(['name' => $request->input('name') . ' Partner']);
            }
        });

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Reseller channel updated successfully!',
            'reseller' => $reseller
        ]);
    }

    /**
     * Delete a reseller channel and its associated user.
     */
    public function deleteReseller($id)
    {
        $reseller = ResellerAccount::find($id);
        if (!$reseller) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Reseller not found.'], 404);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($reseller) {
            // Delete associated user
            $user = \App\Modules\Accounts\Models\User::where('reseller_account_id', $reseller->id)->first();
            if ($user) {
                $user->delete();
            }
            // Delete reseller account
            $reseller->delete();
        });

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Reseller channel deleted successfully.'
        ]);
    }

    /**
     * Reset the password for a reseller's primary user account.
     */
    public function resetResellerPassword(Request $request, $id)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reseller = ResellerAccount::find($id);
        if (!$reseller) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Reseller not found.'], 404);
        }

        $user = \App\Modules\Accounts\Models\User::where('reseller_account_id', $reseller->id)->first();
        if (!$user) {
            return response()->json(['error' => 'NOT_FOUND', 'message' => 'Reseller primary user account not found.'], 404);
        }

        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($request->input('password')),
            'password_changed_at' => now(),
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Reseller password reset successfully.'
        ]);
    }

    /**
     * Get pending Alphanumeric Sender IDs.
     */
    public function listPendingSenders()
    {
        $pending = SenderID::with(['clientAccount', 'mnoApprovals'])
            ->where('status', 'PENDING')
            ->get();

        $formatted = $pending->map(function ($s) {
            return [
                'id' => $s->id,
                'sender_id' => $s->sender_id,
                'status' => $s->status,
                'client_name' => $s->clientAccount ? $s->clientAccount->name : 'Unknown Tenant',
                'mno_approvals' => $s->mnoApprovals,
            ];
        });

        return response()->json([
            'status' => 'SUCCESS',
            'pending_senders' => $formatted
        ]);
    }

    /**
     * Approve a pending Sender ID mask.
     */
    public function approveSender($id)
    {
        $sender = SenderID::find($id);

        if (!$sender) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'The requested Sender ID does not exist.'
            ], 404);
        }

        $sender->update(['status' => 'APPROVED']);

        // Set all operator approvals to approved automatically
        \App\Modules\Messaging\Models\SenderIDMnoApproval::where('sender_id_id', $sender->id)
            ->update(['status' => 'APPROVED', 'reason' => null]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Sender ID mask '{$sender->sender_id}' approved globally across all mobile operators!"
        ]);
    }

    /**
     * Reject a pending Sender ID mask.
     */
    public function rejectSender($id)
    {
        $sender = SenderID::find($id);

        if (!$sender) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'The requested Sender ID does not exist.'
            ], 404);
        }

        $sender->update(['status' => 'REJECTED']);

        // Set all operator approvals to rejected automatically
        \App\Modules\Messaging\Models\SenderIDMnoApproval::where('sender_id_id', $sender->id)
            ->update(['status' => 'REJECTED', 'reason' => 'Administrative policy rejection']);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Sender ID mask '{$sender->sender_id}' was rejected globally."
        ]);
    }

    /**
     * Update MNO-specific approval status for a custom Sender ID mask.
     */
    public function updateMnoApproval(Request $request, $id)
    {
        $sender = SenderID::find($id);

        if (!$sender) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'The requested Sender ID does not exist.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'mno_name' => 'required|string|in:SAFARICOM,AIRTEL,TELKOM',
            'status' => 'required|string|in:APPROVED,REJECTED',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mnoName = $request->input('mno_name');
        $status = $request->input('status');
        $reason = $request->input('reason');

        // Update MNO status row
        \App\Modules\Messaging\Models\SenderIDMnoApproval::updateOrCreate([
            'sender_id_id' => $sender->id,
            'mno_name' => $mnoName,
        ], [
            'status' => $status,
            'reason' => $reason,
        ]);

        // Automatically sync global status based on MNO metrics
        $approvals = \App\Modules\Messaging\Models\SenderIDMnoApproval::where('sender_id_id', $sender->id)->get();
        $statuses = $approvals->pluck('status')->toArray();

        if (count($statuses) >= 3 && !in_array('PENDING', $statuses) && !in_array('REJECTED', $statuses)) {
            $sender->update(['status' => 'APPROVED']);
        } elseif (count($statuses) >= 3 && !in_array('PENDING', $statuses) && !in_array('APPROVED', $statuses)) {
            $sender->update(['status' => 'REJECTED']);
        } else {
            $sender->update(['status' => 'PENDING']);
        }

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "MNO bind status for '{$mnoName}' updated successfully to {$status}."
        ]);
    }

    /**
     * List all Login attempt logs (Immutable Audit Trial).
     */
    public function listAuditLogs()
    {
        $logs = LoginAttempt::orderBy('created_at', 'desc')->take(100)->get();

        return response()->json([
            'status' => 'SUCCESS',
            'audit_logs' => $logs
        ]);
    }

    /**
     * Perform a manual wallet balance adjustment for a client.
     */
    public function adjustWallet(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'reason_code' => 'required|string|in:REFUND_CORRECTION,MANUAL_CREDIT,BILLING_ADJUSTMENT,TOPUP',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ledgerService = app(\App\Modules\Finance\Services\LedgerService::class);
        $adminId = auth()->user()->id;

        try {
            $transaction = $ledgerService->adjust(
                (int) $id,
                (float) $request->input('amount'),
                $request->input('reason_code'),
                $adminId,
                $request->input('description')
            );

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Wallet balance adjusted successfully by supervisor!',
                'transaction' => $transaction
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'ADJUSTMENT_FAILED',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

