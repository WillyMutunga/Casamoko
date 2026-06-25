<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Modules\Messaging\Models\SenderID;
use App\Modules\Messaging\Models\Campaign;
use App\Modules\Messaging\Models\MessageRecord;
use App\Modules\Messaging\Models\OptOutRegistry;
use App\Modules\Messaging\Models\PricingRule;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Messaging\Jobs\SendSMSJob;

class QuickSendController extends Controller
{
    protected LedgerService $ledgerService;

    /**
     * Bind the Ledger Service.
     */
    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Quick Send SMS API pipeline.
     */
    public function quickSend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'msisdn' => 'required|string',
            'message' => 'required|string',
            'sender_id_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json([
                'error' => 'TENANT_NOT_FOUND',
                'message' => 'Your user account must be associated with a corporate client account to dispatch campaigns.'
            ], 403);
        }

        $msisdn = $request->input('msisdn');
        $messageText = $request->input('message');
        $senderIdId = $request->input('sender_id_id');

        // 1. Fetch the selected Sender ID
        $senderID = SenderID::where('id', $senderIdId)
            ->where('client_account_id', $clientAccount->id)
            ->first();

        if (!$senderID) {
            return response()->json([
                'error' => 'INVALID_SENDER_ID',
                'message' => 'The selected Sender ID belongs to another tenant or does not exist.'
            ], 400);
        }

        // Clean & normalize MSISDN
        $cleanMsisdn = preg_replace('/[^0-9]/', '', $msisdn);
        if (str_starts_with($cleanMsisdn, '0')) {
            $cleanMsisdn = '254' . substr($cleanMsisdn, 1);
        }

        // Identify target Mobile Network Operator (MNO)
        $mno = $this->getMnoFromMsisdn($cleanMsisdn);

        // Check if mask is approved on this specific operator
        $approval = \App\Modules\Messaging\Models\SenderIDMnoApproval::where('sender_id_id', $senderID->id)
            ->where('mno_name', $mno)
            ->where('status', 'APPROVED')
            ->first();

        // FR-SID-004: Apply Fallback routing if operator bind is not approved
        if (!$approval && $senderID->fallback_sender_id_id) {
            $fallback = SenderID::where('client_account_id', $clientAccount->id)
                ->where('status', 'APPROVED')
                ->find($senderID->fallback_sender_id_id);

            if ($fallback) {
                $fallbackApproval = \App\Modules\Messaging\Models\SenderIDMnoApproval::where('sender_id_id', $fallback->id)
                    ->where('mno_name', $mno)
                    ->where('status', 'APPROVED')
                    ->first();

                if ($fallbackApproval) {
                    $senderID = $fallback; // Dyn swap to approved fallback
                }
            }
        }

        // Final fail-safe: if the parent status is not APPROVED and MNO status is not approved, block the dispatch
        if ($senderID->status !== 'APPROVED' && (!isset($fallbackApproval) || !$fallbackApproval)) {
            $mnoApprovalCheck = \App\Modules\Messaging\Models\SenderIDMnoApproval::where('sender_id_id', $senderID->id)
                ->where('mno_name', $mno)
                ->where('status', 'APPROVED')
                ->first();

            if (!$mnoApprovalCheck) {
                return response()->json([
                    'error' => 'INVALID_SENDER_ID',
                    'message' => "The selected Sender ID '{$senderID->sender_id}' is not yet approved by compliance for the recipient operator '{$mno}'."
                ], 400);
            }
        }

        // 2. Perform global Opt-Out blocklist validation
        $msisdnHash = Contact::hashMsisdn($cleanMsisdn);
        $isBlacklisted = OptOutRegistry::where('client_account_id', $clientAccount->id)
            ->where('msisdn_hash', $msisdnHash)
            ->exists();

        if ($isBlacklisted) {
            return response()->json([
                'error' => 'RECIPIENT_OPTED_OUT',
                'message' => 'Message dispatch failed. The recipient has unsubscribed from your message feeds.'
            ], 400);
        }

        // 3. Create or update subscriber profile (generates salted privacy hash on save)
        $contact = Contact::updateOrCreate(
            [
                'client_account_id' => $clientAccount->id,
                'msisdn_hash' => $msisdnHash
            ],
            [
                'msisdn' => $cleanMsisdn,
                'name' => $request->input('recipient_name') ?: 'Subscriber'
            ]
        );

        // 4. Fetch dynamic pricing from the Primary Active Route (SMPP Gateway)
        $primaryRoute = \App\Modules\Messaging\Models\Route::where('is_active', true)->orderBy('priority', 'asc')->first();
        $baseCost = $primaryRoute ? (float) $primaryRoute->cost_per_sms : 0.5000;
        $resellerMarkup = 0.0000;

        // Apply reseller markups if the client tenant is managed by a reseller
        if ($clientAccount->resellerAccount) {
            $markupRatio = (float) $clientAccount->resellerAccount->markup_percentage;
            $resellerMarkup += $baseCost * ($markupRatio / 100);
        }

        $totalCost = $baseCost + $resellerMarkup;

        // 5. Create a lightweight tracking Campaign container
        $campaign = Campaign::create([
            'client_account_id' => $clientAccount->id,
            'sender_id_id' => $senderID->id,
            'name' => "Quick Send to " . substr($cleanMsisdn, 0, 6) . "xxxx",
            'template' => $messageText,
            'unicode_type' => (strlen($messageText) !== utf8_decode($messageText)) ? 'UCS-2' : 'GSM-7',
            'status' => 'PROCESSING',
            'sent_count' => 0,
            'delivered_count' => 0,
            'failed_count' => 0,
        ]);

        // 6. Initialize the MessageRecord as QUEUED
        $record = MessageRecord::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'msisdn_hash' => $msisdnHash,
            'route_id' => null,
            'price' => $totalCost,
            'status' => 'QUEUED',
        ]);

        // 7. Atomic balance reservation
        // Throws InsufficientFundsException (HTTP 402) if funds are insufficient, which terminates flow safely.
        $this->ledgerService->debit(
            $clientAccount->id,
            $totalCost,
            'SMS_DISPATCH',
            MessageRecord::class,
            $record->id,
            "Quick Send SMS to contact: {$msisdnHash}"
        );

        // 8. Trigger asynchronous dispatch to worker pool
        SendSMSJob::dispatch($record->id);

        // 9. Fetch Gateway Balance if applicable
        $gatewayBalance = 'N/A';
        if ($mno === 'SAFARICOM') {
            try {
                $gateway = new \App\Modules\Messaging\Services\Gateways\SafaricomSmsGateway();
                $gatewayBalance = $gateway->getBalance() ?? 'N/A';
            } catch (\Exception $e) {
                $gatewayBalance = 'Error: ' . $e->getMessage();
            }
        }

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'SMS queued for immediate dispatch.',
            'campaign_id' => $campaign->id,
            'record_id' => $record->id,
            'total_cost' => $totalCost,
            'balance_after' => $clientAccount->fresh()->wallet_balance,
            'gateway_balance' => $gatewayBalance
        ]);
    }

    /**
     * Resolve carrier MNO name from standard E.164 MSISDN number.
     */
    private function getMnoFromMsisdn($msisdn)
    {
        if (in_array(substr($msisdn, 0, 5), ['25473', '25478']) || str_starts_with($msisdn, '25410')) {
            return 'AIRTEL';
        } elseif (str_starts_with($msisdn, '25477')) {
            return 'TELKOM';
        }
        return 'SAFARICOM';
    }
}
