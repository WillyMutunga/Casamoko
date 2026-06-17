<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Modules\Messaging\Models\SenderID;
use App\Modules\Messaging\Models\SenderIDMnoApproval;
use App\Modules\Messaging\Models\SenderIDBlacklist;
use App\Modules\Accounts\Models\ActivityLog;

class SenderIDController extends Controller
{
    /**
     * Fetch client's Sender ID masks with MNO approvals.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        $senderIds = SenderID::where('client_account_id', $clientAccount->id)
            ->with(['mnoApprovals', 'fallbackSender'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => 'SUCCESS',
            'sender_ids' => $senderIds
        ]);
    }

    /**
     * Submit a new Sender ID mask for approval.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        // Validate basic inputs
        $validator = Validator::make($request->all(), [
            'sender_id' => 'required|string|min:3|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mask = trim($request->input('sender_id'));
        $cleanMask = strtoupper($mask);

        // Alphanumeric validation (max 11 chars) or Numeric validation (long-codes up to 20 digits)
        $isAlphanumeric = preg_match('/^[A-Z0-9\s\-]{3,11}$/i', $mask);
        $isNumeric = preg_match('/^\+?[0-9]{3,20}$/', $mask);

        if (!$isAlphanumeric && !$isNumeric) {
            return response()->json([
                'error' => 'INVALID_MASK_FORMAT',
                'message' => 'Custom Sender ID must be either an alphanumeric brand mask (max 11 characters, letters, numbers, spaces, dashes) or a numeric mobile long-code/virtual number (up to 20 digits).'
            ], 422);
        }

        // Regulatory & Brand protection Blacklist checking
        $blacklisted = SenderIDBlacklist::whereRaw('UPPER(pattern) = ?', [$cleanMask])->first();
        if ($blacklisted) {
            return response()->json([
                'error' => 'SENDER_ID_BLACKLISTED',
                'message' => "The Sender ID mask '{$mask}' violates platform brand and regulatory protection policies: {$blacklisted->reason}"
            ], 422);
        }

        // Ensure mask is unique for this client
        $exists = SenderID::where('client_account_id', $clientAccount->id)
            ->whereRaw('UPPER(sender_id) = ?', [$cleanMask])
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'SENDER_ID_EXISTS',
                'message' => "The Sender ID mask '{$mask}' is already registered under this account."
            ], 400);
        }

        // Create parent mask record
        $senderId = SenderID::create([
            'client_account_id' => $clientAccount->id,
            'sender_id' => $mask,
            'status' => 'PENDING',
        ]);

        // Automatically provision MNO approvals registry for standard carriers
        $mnoNames = ['SAFARICOM', 'AIRTEL', 'TELKOM'];
        foreach ($mnoNames as $mno) {
            SenderIDMnoApproval::create([
                'sender_id_id' => $senderId->id,
                'mno_name' => $mno,
                'status' => 'PENDING',
            ]);
        }

        // Log immutable corporate activity log entry
        ActivityLog::create([
            'client_account_id' => $clientAccount->id,
            'user_id' => $user->id,
            'action' => 'SENDER_CREATED',
            'description' => "Subscriber requested custom Sender ID mask '{$mask}' for brand dispatches.",
            'ip_address' => $request->ip(),
            'username' => $user->email,
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'sender_id' => $senderId->load('mnoApprovals')
        ], 201);
    }

    /**
     * Map a dynamic fallback Sender ID for outbound dispatches.
     */
    public function setFallback(Request $request, $id)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        $sender = SenderID::where('client_account_id', $clientAccount->id)->find($id);
        if (!$sender) {
            return response()->json(['error' => 'SENDER_NOT_FOUND'], 404);
        }

        $validator = Validator::make($request->all(), [
            'fallback_sender_id_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fallbackId = $request->input('fallback_sender_id_id');
        if ($fallbackId) {
            // Confirm the fallback mask belongs to this client and is approved
            $fallback = SenderID::where('client_account_id', $clientAccount->id)
                ->where('status', 'APPROVED')
                ->find($fallbackId);

            if (!$fallback) {
                return response()->json([
                    'error' => 'INVALID_FALLBACK',
                    'message' => 'The designated fallback mask must be approved under this corporate account.'
                ], 400);
            }
        }

        $sender->update([
            'fallback_sender_id_id' => $fallbackId,
        ]);

        ActivityLog::create([
            'client_account_id' => $clientAccount->id,
            'user_id' => $user->id,
            'action' => 'SENDER_UPDATED',
            'description' => "Mapped fallback routing for custom Sender ID mask: {$sender->sender_id}",
            'ip_address' => $request->ip(),
            'username' => $user->email,
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'sender_id' => $sender->load(['mnoApprovals', 'fallbackSender'])
        ]);
    }
}
