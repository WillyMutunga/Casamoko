<?php

namespace App\Modules\Messaging\Services\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SafaricomSmsGateway implements SmsGatewayInterface
{
    protected string $authUrl;
    protected string $sendUrl;
    protected string $balanceUrl;
    protected string $username;
    protected string $password;
    protected string $cpId;
    protected int $packageId;

    /**
     * Initialize the Safaricom Digital SDP client settings.
     */
    public function __construct()
    {
        $authUrl = env('SAFARICOM_SDP_AUTH_URL', 'https://dsdp-apinb.safaricom.com/api/auth/login');
        $sendUrl = env('SAFARICOM_SDP_SEND_URL', 'https://dsdp-apinb.safaricom.com/api/public/CMS/bulksms');
        $balanceUrl = env('SAFARICOM_SDP_BALANCE_URL', 'https://dsdp-apinb.safaricom.com/api/public/CMS/accountBalance');

        // Automatically rewrite old dsvc URLs from .env to the new dsdp-apinb infrastructure
        $this->authUrl = str_replace(['dsvc.safaricom.com:9480', 'dsvc.safaricom.com:8481'], 'dsdp-apinb.safaricom.com', $authUrl);
        $this->sendUrl = str_replace(['dsvc.safaricom.com:9480', 'dsvc.safaricom.com:8481'], 'dsdp-apinb.safaricom.com', $sendUrl);
        $this->balanceUrl = str_replace(['dsvc.safaricom.com:9480', 'dsvc.safaricom.com:8481'], 'dsdp-apinb.safaricom.com', $balanceUrl);

        $this->username = 'casamoko_api';
        $this->password = '5qITcVn81hRion';
        $this->cpId = 'casamokoCons';
        $this->packageId = (int) env('SAFARICOM_SDP_PACKAGE_ID', 4391);
    }

    /**
     * Authenticate and retrieve JWT Bearer token, caching it for high-performance throughput.
     */
    protected function getJwtToken(): ?string
    {
        $cacheKey = 'safaricom_sdp_jwt_token_' . md5($this->username);
        
        return Cache::remember($cacheKey, 3000, function () {
            try {
                $response = Http::timeout(10)->withHeaders([
                    'accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'X-Country' => 'KEN',
                    'Content-Type' => 'application/json'
                ])->post($this->authUrl, [
                    'username' => $this->username,
                    'password' => $this->password
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['token'] ?? $data['accessToken'] ?? $data['data']['token'] ?? null;
                }
                
                Log::error("SafaricomSDP Auth Failed - Status: {$response->status()} | Body: {$response->body()}");
                throw new \Exception("Auth Failed - HTTP {$response->status()}: " . $response->body());
            } catch (\Exception $e) {
                Log::error("SafaricomSDP Auth Exception: " . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Outbound Safaricom Digital SDP CMS Bulk SMS Gateway Dispatcher.
     */
    public function send(string $senderId, string $msisdn, string $message): array
    {
        // Enforce Safaricom's international MSISDN E.164 format without leading plus (+)
        $cleanMsisdn = preg_replace('/[^0-9]/', '', $msisdn);
        if (str_starts_with($cleanMsisdn, '0')) {
            $cleanMsisdn = '254' . substr($cleanMsisdn, 1);
        }

        $uniqueId = 'SAF_CMS_' . uniqid();
        $dlrUrl = 'https://casamoko.co.ke/api/messaging/dlr-webhook';

        // Attempt dispatch with token invalidation retry loop
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $token = $this->getJwtToken();

            if (!$token) {
                return [
                    'status' => 'FAILED',
                    'error_code' => 'AUTH_FAILED',
                    'message' => 'Could not acquire Safaricom JWT Authorization token.'
                ];
            }

            try {
                // Post to CMS Bulk SMS endpoint
                $response = Http::timeout(10)->withHeaders([
                    'accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'X-Country' => 'KEN',
                    'Content-Type' => 'application/json',
                    'X-Authorization' => 'Bearer ' . $token,
                    'Authorization' => 'Bearer ' . $token // Send both variants to ensure compat
                ])->post($this->sendUrl, [
                    'timeStamp' => (int) round(microtime(true) * 1000),
                    'dataSet' => [
                        [
                            'userName' => $this->cpId,
                            'channel' => 'sms',
                            'packageId' => $this->packageId,
                            'oa' => $senderId,
                            'msisdn' => $cleanMsisdn,
                            'message' => $message,
                            'uniqueId' => $uniqueId,
                            'actionResponseURL' => $dlrUrl,
                            'hashed' => 'no'
                        ]
                    ]
                ]);

                // Handle token expiration/revocation cleanly
                if ($response->status() === 401) {
                    $cacheKey = 'safaricom_sdp_jwt_token_' . md5($this->username);
                    Cache::forget($cacheKey);
                    continue;
                }

                if ($response->successful()) {
                    $data = $response->json();
                    $messageId = $data['transactionId'] ?? $data['requestId'] ?? $uniqueId;
                    
                    return [
                        'status' => 'SENT',
                        'message_id' => $messageId,
                        'network_status_code' => 'DELIVRD'
                    ];
                }

                Log::error("SafaricomSDP CMS SMS Dispatch [Failed] - Status: {$response->status()} | Body: {$response->body()}");

                return [
                    'status' => 'FAILED',
                    'error_code' => 'HTTP_' . $response->status(),
                    'message' => $response->body()
                ];

            } catch (\Exception $e) {
                Log::error("SafaricomSDP CMS SMS Dispatch [Exception] - Trace: " . $e->getMessage());

                return [
                    'status' => 'FAILED',
                    'error_code' => 'GATEWAY_CONNECTION_EXPIRED',
                    'message' => $e->getMessage()
                ];
            }
        }

        return [
            'status' => 'FAILED',
            'error_code' => 'GATEWAY_DISPATCH_TIMEOUT',
            'message' => 'Failed to dispatch SMS after multiple token retry attempts.'
        ];
    }

    /**
     * Fetch Account Balance from Safaricom SDP.
     */
    public function getBalance(): ?string
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $token = $this->getJwtToken();
            if (!$token) {
                return null;
            }

            try {
                $response = Http::timeout(10)->withHeaders([
                    'accept' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'X-Country' => 'KEN',
                    'Content-Type' => 'application/json',
                    'X-Authorization' => 'Bearer ' . $token,
                    'Authorization' => 'Bearer ' . $token
                ])->get($this->balanceUrl . '?spId=' . $this->cpId);

                if ($response->status() === 401) {
                    $cacheKey = 'safaricom_sdp_jwt_token_' . md5($this->username);
                    Cache::forget($cacheKey);
                    continue;
                }

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['balance'] ?? $data['data']['balance'] ?? 'Unknown';
                }
                return null;
            } catch (\Exception $e) {
                Log::error("SafaricomSDP Balance Fetch Exception: " . $e->getMessage());
                return null;
            }
        }
        return null;
    }
}
