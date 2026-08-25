<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOtpSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $phone;
    protected $code;
    protected $senderId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $phone, string $code, string $senderId = 'CASAMOKO')
    {
        $this->phone = $phone;
        $this->code = $code;
        $this->senderId = $senderId ?: 'CASAMOKO';
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $message = "Your Casamoko authentication code is: {$this->code}. Valid for 15 minutes. Do not share this code with anyone.";
            $gateway = new \App\Modules\Messaging\Services\Gateways\SafaricomSmsGateway();
            $result = $gateway->send($this->senderId, $this->phone, $message);
            Log::info("OTP SMS dispatched to {$this->phone} via {$this->senderId}. Result: " . json_encode($result));
        } catch (\Exception $e) {
            Log::error('Failed to send OTP SMS to ' . $this->phone . ': ' . $e->getMessage());
        }
    }
}
