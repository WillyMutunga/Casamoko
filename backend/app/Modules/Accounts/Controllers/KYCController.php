<?php

namespace App\Modules\Accounts\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Modules\Accounts\Models\ClientAccount;
use App\Modules\Accounts\Models\ActivityLog;

class KYCController extends Controller
{
    /**
     * Upload organization compliance files (FR-UAM-001).
     */
    public function uploadKYC(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|string|in:BUSINESS_PERMIT,TAX_CERTIFICATE,ID_PASSPORT',
            'file_name' => 'required|string',
            'file_data' => 'required|string', // Base64 encoded payload
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json([
                'error' => 'TENANT_NOT_FOUND',
                'message' => 'Your profile is not linked to any organization tenant.'
            ], 403);
        }

        // Store file details inside kyc_metadata JSON column
        $currentKyc = $clientAccount->kyc_metadata ?: [];
        $currentKyc[$request->input('document_type')] = [
            'file_name' => $request->input('file_name'),
            'uploaded_at' => now()->toIso8601String(),
            'file_size_mock' => strlen($request->input('file_data')) * 0.75 . ' bytes'
        ];

        $clientAccount->update([
            'kyc_metadata' => $currentKyc,
            'status' => 'PENDING' // Set back to pending for admin verification
        ]);

        // Write immutable audit log
        ActivityLog::log('KYC_UPLOADED', "Uploaded document type '{$request->input('document_type')}' for organizing tenant approval.");

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'KYC compliance document uploaded successfully. Awaiting System Supervisor verification.',
            'kyc_metadata' => $currentKyc
        ]);
    }

    /**
     * Suspend or Reactivate Client Account (FR-UAM-007).
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:APPROVED,SUSPENDED,PENDING',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $clientAccount = ClientAccount::find($id);

        if (!$clientAccount) {
            return response()->json([
                'error' => 'TENANT_NOT_FOUND',
                'message' => 'The requested organization client account does not exist.'
            ], 404);
        }

        $oldStatus = $clientAccount->status;
        $newStatus = $request->input('status');

        $clientAccount->update([
            'status' => $newStatus
        ]);

        // Write immutable audit log
        ActivityLog::log('ACCOUNT_SUSPENSION_TOGGLED', "Changed tenant account #{$clientAccount->id} status from '{$oldStatus}' to '{$newStatus}'.");

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Corporate client '{$clientAccount->name}' status updated to '{$newStatus}' successfully!",
            'client' => $clientAccount
        ]);
    }
}
