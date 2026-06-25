<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Messaging\Models\MessageRecord;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Messaging\Jobs\SendSMSJob;
use Illuminate\Support\Facades\Log;

class ApiController extends Controller
{
    /**
     * Dispatch SMS via Developer API
     */
    public function sendSms(Request $request, LedgerService $ledgerService)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
            'sender_id' => 'nullable|string'
        ]);

        $clientAccount = $request->attributes->get('clientAccount');

        // Estimate cost based on segment length
        $encoding = CampaignController::getUnicodeType($request->message);
        $segments = CampaignController::getSegmentCount($request->message, $encoding);
        
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

        // Format phone
        $phone = preg_replace('/[^0-9]/', '', $request->phone);
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
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

        // Override message template in campaign context just for the job
        // But SendSMSJob uses $campaign->template!
        // Oh wait. SendSMSJob fetches $campaign->template. 
        // We need a specific API dispatcher Job or we need to pass the message directly to SendSMSJob.
        // Let's modify SendSMSJob to accept optional messageText and senderId instead of falling back to campaign.
        // Actually, for this demonstration, we can just use the Gateway directly if it's an API call, 
        // OR we can enqueue an ApiSendSMSJob. Let's create an ApiSendSMSJob.

        ApiSendSMSJob::dispatch($record->id, $request->message, $request->sender_id ?: 'CASAMOKO');

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Message queued for delivery.',
            'message_id' => $record->id,
            'cost' => $totalCost
        ]);
    }
}
