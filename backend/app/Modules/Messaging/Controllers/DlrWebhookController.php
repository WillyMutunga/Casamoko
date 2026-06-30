<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Modules\Messaging\Models\MessageRecord;
use App\Modules\Finance\Services\LedgerService;

class DlrWebhookController extends Controller
{
    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Handle incoming carrier Delivery Receipt (DLR) updates.
     */
    public function handle(Request $request)
    {
        $payload = $request->all();
        Log::info('DLR Webhook Received: ', $payload);

        // Extract ID - Support generic 'mno_message_id', or Safaricom 'uniqueId', 'correlator', 'messageId', 'traceUniqueID'
        $mnoMessageId = $payload['mno_message_id'] ?? $payload['uniqueId'] ?? $payload['correlator'] ?? $payload['messageId'] ?? $payload['traceUniqueID'] ?? null;

        // Extract Status - Support generic 'status', or Safaricom 'deliveryStatus', 'statusDesc'
        $rawStatus = $payload['status'] ?? $payload['deliveryStatus'] ?? $payload['statusDesc'] ?? 'PENDING';
        
        $newStatus = 'PENDING';
        $networkStatusCode = $payload['network_status_code'] ?? 'UNKNOWN';

        $rawStatusUpper = strtoupper($rawStatus);

        if (in_array($rawStatusUpper, ['DELIVERED', 'DELIVEREDTOTERMINAL', 'SUCCESS', '1', 1])) {
            $newStatus = 'DELIVERED';
            $networkStatusCode = 'DELIVRD';
        } elseif (in_array($rawStatusUpper, ['FAILED', 'DELIVERYFAILURE', 'EXPIRED', 'REJECTED', 'UNDELIV', '2', 2])) {
            $newStatus = 'FAILED';
            $networkStatusCode = $rawStatusUpper;
        }

        if (!$mnoMessageId) {
            return response()->json(['success' => false, 'message' => 'Missing message identifier'], 400);
        }

        // Locate MessageRecord by MNO message ID
        $record = MessageRecord::with(['campaign.clientAccount'])->where('mno_message_id', $mnoMessageId)->first();

        if (!$record) {
            Log::warning("DlrWebhookController: MessageRecord with mno_message_id '{$mnoMessageId}' not found");
            return response()->json([
                'success' => false,
                'message' => 'Message record not found'
            ], 404);
        }

        $oldStatus = $record->status;

        if ($oldStatus === $newStatus) {
            return response()->json([
                'success' => true,
                'message' => 'Status already up to date',
                'record_id' => $record->id
            ]);
        }

        // Update the record status
        $record->update([
            'status' => $newStatus,
            'network_status_code' => $networkStatusCode
        ]);

        $campaign = $record->campaign;
        $clientAccount = $campaign ? $campaign->clientAccount : null;

        // Manage Campaign stats updates
        if ($campaign) {
            if ($newStatus === 'DELIVERED') {
                if ($oldStatus === 'FAILED') {
                    $campaign->decrement('failed_count');
                }
                $campaign->increment('delivered_count');
            } elseif ($newStatus === 'FAILED') {
                // If it transitioned from SENT (which mocked delivery_count) to FAILED
                if ($oldStatus === 'SENT' || $oldStatus === 'DELIVERED') {
                    $campaign->decrement('delivered_count');
                }
                $campaign->increment('failed_count');
            }
        }

        // Refund Loop: If new status is FAILED and old was not FAILED, issue refund
        if ($newStatus === 'FAILED' && $oldStatus !== 'FAILED' && $record->price > 0 && $clientAccount) {
            try {
                $this->ledgerService->credit(
                    $clientAccount->id,
                    (float) $record->price,
                    'REFUND',
                    MessageRecord::class,
                    $record->id,
                    "Carrier DLR failure refund for record #{$record->id}."
                );
                Log::info("DlrWebhookController [ATOMIC_REFUND]: Refunded {$record->price} to client #{$clientAccount->id} for failed DLR record #{$record->id}");
            } catch (\Exception $e) {
                Log::error("DlrWebhookController [ATOMIC_REFUND_ERROR]: Could not refund client #{$clientAccount->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Message record updated from {$oldStatus} to {$newStatus}",
            'record_id' => $record->id
        ]);
    }
}
