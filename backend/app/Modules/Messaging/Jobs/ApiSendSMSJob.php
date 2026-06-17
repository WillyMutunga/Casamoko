<?php

namespace App\Modules\Messaging\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Modules\Messaging\Models\MessageRecord;
use App\Modules\Messaging\Services\IntelligentRouteSelector;
use App\Modules\Finance\Services\LedgerService;
use Illuminate\Support\Facades\Log;

class ApiSendSMSJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $messageRecordId;
    public $messageText;
    public $senderId;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct($messageRecordId, $messageText, $senderId)
    {
        $this->messageRecordId = $messageRecordId;
        $this->messageText = $messageText;
        $this->senderId = $senderId;
    }

    public function handle(IntelligentRouteSelector $router, LedgerService $ledger)
    {
        $record = MessageRecord::with(['contact', 'campaign.clientAccount'])->find($this->messageRecordId);

        if (!$record || $record->status !== 'QUEUED') {
            return;
        }

        $record->update(['status' => 'PROCESSING']);

        try {
            // Find optimal provider
            $provider = $router->selectBestRoute($record->contact->msisdn);
            $gatewayClass = app($provider->gateway_class);

            // Execute dispatch
            $response = $gatewayClass->send(
                $record->contact->msisdn,
                $this->messageText,
                $this->senderId
            );

            if ($response['status'] === 'SUCCESS') {
                $record->update([
                    'status' => 'SENT',
                    'provider_message_id' => $response['message_id'] ?? null
                ]);
            } else {
                throw new \Exception($response['message'] ?? 'Unknown gateway error');
            }

        } catch (\Exception $e) {
            Log::error("API SendSMSJob Failed [Record {$this->messageRecordId}]: " . $e->getMessage());

            $record->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage()
            ]);

            // Refund
            try {
                $ledger->credit(
                    $record->campaign->client_account_id,
                    $record->price,
                    'REFUND',
                    MessageRecord::class,
                    $record->id,
                    "Refund for failed API SMS to " . $record->contact->msisdn
                );
                $record->update(['status' => 'FAILED_REFUNDED']);
            } catch (\Exception $refundE) {
                Log::error("Refund failed for Record {$this->messageRecordId}: " . $refundE->getMessage());
            }

            if ($this->attempts() < $this->tries) {
                $record->update(['status' => 'QUEUED']);
                $this->release($this->backoff[$this->attempts() - 1]);
            }
        }
    }
}
