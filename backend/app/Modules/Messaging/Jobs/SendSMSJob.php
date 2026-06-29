<?php

namespace App\Modules\Messaging\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Modules\Messaging\Models\MessageRecord;
use App\Modules\Messaging\Services\Gateways\SafaricomSmsGateway;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Messaging\Services\IntelligentRouteSelector;
use Illuminate\Support\Facades\Redis;

class SendSMSJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $recordId;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(int $recordId)
    {
        $this->recordId = $recordId;
    }

    /**
     * Execute the job.
     */
    public function handle(LedgerService $ledgerService, IntelligentRouteSelector $routeSelector)
    {
        // 1. Fetch Message Record with relations
        $record = MessageRecord::with(['campaign.senderId', 'campaign.clientAccount'])->find($this->recordId);

        if (!$record || !in_array($record->status, ['QUEUED', 'PENDING'])) {
            return;
        }

        $campaign = $record->campaign;
        $clientAccount = $campaign ? $campaign->clientAccount : null;
        $senderIdText = ($campaign && $campaign->senderId) ? $campaign->senderId->sender_id : 'CASAMOKO';
        $messageText = $campaign ? $campaign->template : 'Notification';
        
        if ($campaign && !empty($campaign->opt_out_message)) {
            $messageText .= "\n" . $campaign->opt_out_message;
        }

        // 2. Resolve the subscriber E.164 number.
        $contact = $record->contact;
        $recipientMsisdn = $contact ? $contact->msisdn : null;

        if (!$recipientMsisdn) {
            $record->update([
                'status' => 'FAILED',
                'network_status_code' => 'MISSING_RECIPIENT_NUMBER'
            ]);
            
            if ($campaign) {
                $campaign->increment('failed_count');
            }
            return;
        }

        $tpsLimit = $campaign ? ($campaign->tps_limit ?: 10) : 10;
        
        $bestRoute = null;

        try {

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts("campaign_tps_{$campaign->id}", $tpsLimit)) {
            Log::debug("SendSMSJob: TPS limit exceeded for record #{$this->recordId}, requeuing.");
            return $this->release(1);
        }

        \Illuminate\Support\Facades\RateLimiter::hit("campaign_tps_{$campaign->id}", 1);

        try {
            // 3. Select Route using Intelligent Route Selector (QoS + Cost + Failover)
            $bestRoute = $routeSelector->selectBestRoute($recipientMsisdn);

            if (!$bestRoute) {
                throw new \Exception("NO_ROUTE_AVAILABLE");
            }

            // 4. Instantiate carrier gateway based on route details
            if ($bestRoute->destination_network === 'SAFARICOM') {
                $gateway = new SafaricomSmsGateway();
            } else {
                throw new \Exception("UNSUPPORTED_ROUTE_NETWORK");
            }

            // 5. Trigger dispatch to carrier gateway
            $result = $gateway->send($senderIdText, $recipientMsisdn, $messageText, $record->link_id ?? null);

            if ($result['status'] !== 'SENT') {
                $errorString = $result['error_code'] ?? 'GATEWAY_ERROR';
                if (isset($result['message'])) {
                    $errorString .= ' - ' . (is_array($result['message']) ? json_encode($result['message']) : $result['message']);
                }
                throw new \Exception($errorString);
            }

            // Update MessageRecord to SENT with operator receipt IDs and route used
            $record->update([
                'status' => 'SENT',
                'mno_message_id' => $result['message_id'],
                'network_status_code' => $result['network_status_code'] ?? 'DELIVRD',
                'route_id' => $bestRoute->id
            ]);

            if ($campaign) {
                $campaign->increment('sent_count');
            }

            Log::info("SendSMSJob: Successfully sent record #{$record->id} using route '{$bestRoute->name}'");

        } catch (\Exception $e) {
            // Bubble up the exception to the outer catch block
            throw $e;
        }

        } catch (\Exception $e) {
            // Degrade route if applicable to trigger failover on subsequent dispatches
            if ($bestRoute) {
                $routeSelector->degradeRoute($bestRoute);
            }

            // 6. Exponential backoff retry logic
            $terminalErrors = ['SC0011', 'AUTH_FAILED', 'MISSING_REQUIRED_FIELD'];
            $isTerminal = false;
            foreach ($terminalErrors as $termErr) {
                if (str_contains($e->getMessage(), $termErr)) {
                    $isTerminal = true;
                    break;
                }
            }
            
            if (!$isTerminal && $this->attempts() < $this->tries) {
                $backoff = 2 ** $this->attempts();
                Log::warning("SendSMSJob: Dispatch failed for record #{$record->id} due to {$e->getMessage()}, retrying in {$backoff}s. Attempt: " . $this->attempts());
                
                // If using sync driver, it drops released jobs. Mark as failed immediately to show the error.
                if (config('queue.default') === 'sync') {
                    $isTerminal = true;
                } else {
                    $this->release($backoff);
                    return;
                }
            }

            // Terminal failure after max tries or if error is terminal (or sync driver)
            $record->update([
                'status' => 'FAILED',
                'network_status_code' => $e->getMessage()
            ]);

            if ($campaign) {
                $campaign->increment('failed_count');
            }

            // 7. ATOMIC REFUND PIPELINE
            if ($clientAccount && $record->price > 0) {
                try {
                    $ledgerService->credit(
                        $clientAccount->id,
                        (float) $record->price,
                        'REFUND',
                        MessageRecord::class,
                        $record->id,
                        "Failed SMS dispatch refund for record #{$record->id}."
                    );
                    Log::channel('stack')->info("ATOMIC_REFUND [Success] - Credited back {$record->price} to client #{$clientAccount->id} for failed SMS record #{$record->id}");
                } catch (\Exception $refundEx) {
                    Log::channel('stack')->error("ATOMIC_REFUND [Failed] - Could not credit client #{$clientAccount->id} for failed SMS record #{$record->id}: " . $refundEx->getMessage());
                }
            }
        }
    }
}
