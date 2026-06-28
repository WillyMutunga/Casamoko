<?php

namespace App\Modules\Messaging\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Modules\Accounts\Models\ClientAccount;

class DispatchClientWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    
    // Exponential backoff configuration
    public $backoff = [10, 60, 300, 1800, 7200]; // 10s, 1m, 5m, 30m, 2h

    protected $webhookUrl;
    protected $payload;
    protected $clientAccountId;

    /**
     * Create a new job instance.
     */
    public function __construct($webhookUrl, array $payload, $clientAccountId)
    {
        $this->webhookUrl = $webhookUrl;
        $this->payload = $payload;
        $this->clientAccountId = $clientAccountId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $clientAccount = ClientAccount::find($this->clientAccountId);
        
        if (!$clientAccount) {
            Log::warning("DispatchClientWebhookJob: ClientAccount {$this->clientAccountId} not found. Dropping webhook.");
            return;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Sign payload if secret is configured
        if (!empty($clientAccount->webhook_secret)) {
            $jsonPayload = json_encode($this->payload);
            $signature = hash_hmac('sha256', $jsonPayload, $clientAccount->webhook_secret);
            $headers['X-Casamoko-Signature'] = $signature;
        }

        try {
            $response = Http::timeout(10)->withHeaders($headers)->post($this->webhookUrl, $this->payload);

            if (!$response->successful()) {
                Log::warning("DispatchClientWebhookJob: Failed HTTP {$response->status()} from {$this->webhookUrl}");
                $this->release($this->backoff[$this->attempts() - 1] ?? 7200);
            } else {
                Log::info("DispatchClientWebhookJob: Successfully delivered to {$this->webhookUrl}");
            }
        } catch (\Exception $e) {
            Log::error("DispatchClientWebhookJob: Exception delivering to {$this->webhookUrl}: " . $e->getMessage());
            $this->release($this->backoff[$this->attempts() - 1] ?? 7200);
        }
    }
}
