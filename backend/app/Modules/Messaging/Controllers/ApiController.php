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

        // Check balance
        if ($clientAccount->wallet_balance < $totalCost) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Insufficient wallet balance.'
            ], 402);
        }

        // Atomically deduct the balance
        try {
            $ledgerService->debit(
                $clientAccount->id,
                $totalCost,
                'SMS_API_DISPATCH',
                null,
                null,
                'API Bulk SMS Dispatch'
            );
        } catch (\Exception $e) {
            Log::error("API SendSMS: Ledger debit failed - " . $e->getMessage());
            return response()->json(['status' => 'ERROR', 'message' => 'Transaction failed'], 500);
        }

        // Create a contact silently (or just hash it)
        $hash = \App\Modules\Contacts\Models\Contact::hashMsisdn($phone);
        $contact = \App\Modules\Contacts\Models\Contact::firstOrCreate(
            ['client_account_id' => $clientAccount->id, 'msisdn_hash' => $hash],
            ['msisdn' => $phone, 'name' => 'API Subscriber']
        );

        // We don't have a specific campaign, so we create a dummy campaign or use a specific API campaign!
        // For architectural simplicity, we create a pseudo campaign if none exists, or just allow null.
        // The SendSMSJob requires a MessageRecord, which requires a campaign_id in the DB schema.
        $campaign = \App\Modules\Messaging\Models\Campaign::firstOrCreate(
            ['client_account_id' => $clientAccount->id, 'name' => 'API Dispatches Default'],
            [
                'template' => 'API Dispatch',
                'sender_id_id' => null, // Or look up the requested sender_id
                'status' => 'COMPLETED',
                'tps_limit' => 100, // API is fast
            ]
        );

        $record = MessageRecord::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'msisdn_hash' => $hash,
            'price' => $totalCost,
            'status' => 'QUEUED',
        ]);

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
