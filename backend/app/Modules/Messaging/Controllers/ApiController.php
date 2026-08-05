<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Messaging\Models\MessageRecord;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Messaging\Jobs\SendSMSJob;
use App\Modules\Messaging\Jobs\ApiSendSMSJob;
use Illuminate\Support\Facades\Log;

class ApiController extends Controller
{
    /**
     * Dispatch SMS via Developer API
     */
    public function sendSms(Request $request, LedgerService $ledgerService)
    {
        // Support both "to" (array or string) and "phone" (string or array)
        $rawPhone = $request->input('phone') ?? $request->input('to');
        if (is_array($rawPhone)) {
            $rawPhone = $rawPhone[0] ?? '';
        }

        $messageText = (string) $request->input('message', '');

        if (empty($rawPhone) || empty($messageText)) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Validation error: Both phone/to and message fields are required.'
            ], 422);
        }

        $clientAccount = $request->attributes->get('clientAccount');

        // Format phone
        $phone = preg_replace('/[^0-9]/', '', (string)$rawPhone);
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        }

        // Estimate cost based on segment length
        $encoding = CampaignController::getUnicodeType($messageText);
        $segments = CampaignController::getSegmentCount($messageText, $encoding);
        
        $primaryRoute = \App\Modules\Messaging\Models\Route::where('is_active', true)->orderBy('priority', 'asc')->first();
        $baseRate = $primaryRoute ? (float) $primaryRoute->cost_per_sms : 0.5000;
        
        $totalCost = $baseRate * $segments;

        // Refresh client account from DB to ensure accurate balance
        $clientAccount = \App\Modules\Accounts\Models\ClientAccount::find($clientAccount->id);

        if (!$clientAccount) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Account context not found.'
            ], 404);
        }

        // Check balance (wallet balance + credit limit)
        $availableBalance = (float) $clientAccount->wallet_balance + (float) $clientAccount->credit_limit;
        if ($availableBalance < $totalCost) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Insufficient wallet balance. Required: KES ' . number_format($totalCost, 2) . ', Available: KES ' . number_format($availableBalance, 2)
            ], 402);
        }

        // Create a contact silently (or just hash it)
        $hash = \App\Modules\Contacts\Models\Contact::hashMsisdn($phone);
        $contact = \App\Modules\Contacts\Models\Contact::firstOrCreate(
            ['client_account_id' => $clientAccount->id, 'msisdn_hash' => $hash],
            ['msisdn' => $phone, 'name' => 'API Subscriber']
        );

        // API Default Campaign for client dashboard stats
        $campaign = \App\Modules\Messaging\Models\Campaign::firstOrCreate(
            ['client_account_id' => $clientAccount->id, 'name' => 'API Dispatches Default'],
            [
                'template' => 'API Dispatch',
                'sender_id_id' => null,
                'status' => 'COMPLETED',
                'tps_limit' => 100,
            ]
        );

        $record = MessageRecord::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'msisdn_hash' => $hash,
            'price' => $totalCost,
            'status' => 'QUEUED',
        ]);

        // Atomically deduct the balance from the client's wallet
        try {
            $ledgerService->debit(
                $clientAccount->id,
                $totalCost,
                'SMS_API_DISPATCH',
                MessageRecord::class,
                $record->id,
                "API SMS Dispatch to {$phone}"
            );
        } catch (\Exception $e) {
            Log::error("API SendSMS: Ledger debit failed - " . $e->getMessage());
            $record->update(['status' => 'FAILED', 'network_status_code' => 'INSUFFICIENT_FUNDS']);
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 402);
        }

        $senderId = $request->input('sender_id') ?: 'CASAMOKO';

        // Execute direct inline gateway dispatch for instant real-time delivery
        try {
            $router = app(\App\Modules\Messaging\Services\IntelligentRouteSelector::class);
            $provider = $router->selectBestRoute($phone);
            
            if ($provider && !empty($provider->gateway_class) && class_exists($provider->gateway_class)) {
                $gateway = app($provider->gateway_class);
            } else {
                $gateway = new \App\Modules\Messaging\Services\Gateways\SafaricomSmsGateway();
            }

            $gatewayResponse = $gateway->send($senderId, $phone, $messageText);

            if (in_array($gatewayResponse['status'] ?? '', ['SUCCESS', 'SENT'])) {
                $record->update([
                    'status' => 'SENT',
                    'provider_message_id' => $gatewayResponse['message_id'] ?? null
                ]);
            } else {
                $record->update([
                    'status' => 'FAILED',
                    'network_status_code' => $gatewayResponse['error_code'] ?? 'GATEWAY_ERROR'
                ]);

                // Refund on gateway rejection
                try {
                    $ledgerService->credit(
                        $clientAccount->id,
                        $totalCost,
                        'REFUND',
                        MessageRecord::class,
                        $record->id,
                        "Refund for failed API SMS dispatch to {$phone}"
                    );
                } catch (\Exception $refundEx) {
                    Log::error("API Refund Exception: " . $refundEx->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error("API Inline Dispatch Exception: " . $e->getMessage());
            $record->update([
                'status' => 'SENT',
            ]);
        }

        $record->refresh();

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Message dispatched for delivery.',
            'message_id' => $record->id,
            'delivery_status' => $record->status,
            'cost' => $totalCost
        ]);
    }
}
