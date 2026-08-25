<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWelcomeSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $phone;
    protected $tenantName;
    protected $email;
    protected $senderId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $phone, string $tenantName, string $email, string $senderId = 'CASAMOKO')
    {
        $this->phone = $phone;
        $this->tenantName = $tenantName;
        $this->email = $email;
        $this->senderId = $senderId ?: 'CASAMOKO';
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $message = "Welcome to Casamoko! Your corporate account for '{$this->tenantName}' has been provisioned. Login at https://casamoko.co.ke using email: {$this->email}.";
            $gateway = new \App\Modules\Messaging\Services\Gateways\SafaricomSmsGateway();
            $result = $gateway->send($this->senderId, $this->phone, $message);
            Log::info("Welcome SMS dispatched to {$this->phone} via {$this->senderId}. Result: " . json_encode($result));
        } catch (\Exception $e) {
            Log::error('Failed to send Welcome SMS to ' . $this->phone . ': ' . $e->getMessage());
        }
    }
}
