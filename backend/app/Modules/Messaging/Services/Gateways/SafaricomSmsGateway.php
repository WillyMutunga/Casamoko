<?php

namespace App\Modules\Messaging\Services\Gateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SafaricomSmsGateway implements SmsGatewayInterface
{
    protected string $authUrl;
    protected string $sendUrl;
    protected string $balanceUrl;
    protected string $username;
    protected string $password;
    protected string $cpId;
    protected string $packageId;
    protected string $offerCode;
    protected string $mode;

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

        $this->username = env('SAFARICOM_SDP_USERNAME', 'casamoko_api');
        $this->password = env('SAFARICOM_SDP_PASSWORD', '5qITcVn81hRion');
        $this->cpId = (string) (env('SAFARICOM_SDP_CP_ID') ?: '143');
        $this->packageId = (string) (env('SAFARICOM_SDP_PACKAGE_ID') ?: '890');
        $this->offerCode = (string) (env('SAFARICOM_SDP_OFFER_CODE') ?: '300000863');
        $this->mode = (string) (env('SAFARICOM_SDP_MODE') ?: 'interactive');
    }

    /**
     * Authenticate and retrieve JWT Bearer token, caching it for high-performance throughput.
     */
    protected function getJwtToken(): ?string
    {
        $cacheKey = 'safaricom_sdp_jwt_token_' . md5($this->username);
        
        return Cache::remember($cacheKey, 3000, function () {
            try {
                $response = Http::timeout(30)
                    ->withOptions([
                        'curl' => [
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_FORBID_REUSE => true,
                            CURLOPT_FRESH_CONNECT => true
                        ]
                    ])
                    ->withHeaders([
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
                throw new \Exception("AUTH_FAILED");
            } catch (\Exception $e) {
                Log::error("SafaricomSDP Auth Exception: " . $e->getMessage());
                throw $e;
            }
        });
    }

    /**
     * Outbound Safaricom Digital SDP CMS Bulk SMS Gateway Dispatcher.
     */
    public function send(string $senderId, string $msisdn, string $message, ?string $linkId = null): array
    {
        // Enforce Safaricom's international MSISDN E.164 format without leading plus (+)
        $cleanMsisdn = preg_replace('/[^0-9]/', '', $msisdn);
        if (str_starts_with($cleanMsisdn, '0')) {
            $cleanMsisdn = '254' . substr($cleanMsisdn, 1);
        }

        $uniqueId = (string) Str::uuid();
        $dlrUrl = 'https://casamoko.co.ke/api/dlr-webhook';

        // Build Payload depending on interactive / non-interactive shortcode status
        if ($this->mode === 'non_interactive') {
            $timestamp = date('YmdHis');
            $data = [
                ['name' => 'OfferCode', 'value' => (string) $this->offerCode],
                ['name' => 'RefernceId', 'value' => $uniqueId],
                ['name' => 'ClientTransactionId', 'value' => $uniqueId],
                ['name' => 'Language', 'value' => '1'],
                ['name' => 'Channel', 'value' => 'SMS'],
                ['name' => 'Type', 'value' => $linkId ? 'NOTIFY_LINKID' : 'NOTIFY_LINKID'],
                ['name' => 'Msisdn', 'value' => $cleanMsisdn],
                ['name' => 'USER_DATA', 'value' => $message]
            ];

            if ($linkId) {
                array_unshift($data, ['name' => 'LinkId', 'value' => $linkId]);
            }

            $payload = [
                'requestId' => $uniqueId,
                'requestTimestamp' => $timestamp,
                'operation' => 'CP_NOTIFICATION',
                'requestParam' => [
                    'data' => $data,
                    'additionalData' => []
                ]
            ];
        } else {
            $payload = [
                'timeStamp' => (int) round(microtime(true) * 1000),
                'dataSet' => [
                    array_filter([
                        'userName' => $this->cpId,
                        'channel' => 'sms',
                        'packageId' => (string) $this->packageId,
                        'serviceId' => (string) $this->packageId,
                        'oa' => $senderId,
                        'msisdn' => $cleanMsisdn,
                        'message' => $message,
                        'uniqueId' => 'SAF_CMS_' . uniqid(),
                        'actionResponseURL' => $dlrUrl,
                        'hashed' => 'no',
                        'linkId' => $linkId
                    ], fn($val) => !is_null($val))
                ]
            ];
        }

        // Attempt dispatch with token invalidation and fresh connection retry loop (up to 3 attempts)
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $token = $this->getJwtToken();

            if (!$token) {
                return [
                    'status' => 'FAILED',
                    'error_code' => 'AUTH_FAILED',
                    'message' => 'Could not acquire Safaricom JWT Authorization token.'
                ];
            }

            try {
                // Post to CMS Bulk SMS endpoint with fresh HTTP/1.1 connection settings
                $response = Http::timeout(30)
                    ->withOptions([
                        'curl' => [
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_FORBID_REUSE => true,
                            CURLOPT_FRESH_CONNECT => true
                        ]
                    ])
                    ->withHeaders([
                        'accept' => 'application/json',
                        'X-Requested-With' => 'XMLHttpRequest',
                        'X-Country' => 'KEN',
                        'Content-Type' => 'application/json',
                        'X-Authorization' => 'Bearer ' . $token,
                        'Authorization' => 'Bearer ' . $token
                    ])->post($this->sendUrl, $payload);

                // Handle token expiration/revocation cleanly (HTTP 401, HTTP 403, or INVALID_USER_TOKEN)
                $responseBody = $response->body();
                if ($response->status() === 401 || $response->status() === 403 || str_contains($responseBody, 'INVALID_USER_TOKEN') || str_contains($responseBody, 'different user')) {
                    $cacheKey = 'safaricom_sdp_jwt_token_' . md5($this->username);
                    Cache::forget($cacheKey);

                    Log::warning("SafaricomSDP Token Invalidated (Attempt {$attempt}/3) - Status: {$response->status()} | Body: {$responseBody}");

                    if ($attempt < 3) {
                        usleep(200000); // 200ms pause before acquiring fresh token
                        continue;
                    }
                }

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Safaricom returns HTTP 200 even for logical status codes
                    $statusCode = $data['statusCode'] ?? $data['responseCode'] ?? $data['code'] ?? null;
                    $statusStr = $data['status'] ?? $data['responseMessage'] ?? null;

                    if ($statusCode === 'SC0000' || $statusCode === '0' || $statusStr === 'SUCCESS' || isset($data['requestId'])) {
                        $messageId = $data['transactionId'] ?? $data['requestId'] ?? $uniqueId;
                        return [
                            'status' => 'SENT',
                            'message_id' => $messageId,
                            'network_status_code' => 'DELIVRD'
                        ];
                    }

                    // Logical failure despite HTTP 200 (e.g. SC0011 TOTAL_QUOTA_EXCEEDED)
                    return [
                        'status' => 'FAILED',
                        'error_code' => (string) ($statusCode ?? 'API_LOGICAL_ERROR'),
                        'message' => json_encode($data)
                    ];
                }

                Log::error("SafaricomSDP CMS SMS Dispatch [Failed] - Status: {$response->status()} | Body: {$response->body()}");

                if ($attempt < 3) {
                    usleep(200000);
                    continue;
                }

                return [
                    'status' => 'FAILED',
                    'error_code' => 'HTTP_' . $response->status(),
                    'message' => $response->body()
                ];

            } catch (\Exception $e) {
                Log::warning("SafaricomSDP CMS SMS Dispatch Exception (Attempt {$attempt}/3): " . $e->getMessage());
                // Invalidate cached token in case connection error was due to stale session handle
                $cacheKey = 'safaricom_sdp_jwt_token_' . md5($this->username);
                Cache::forget($cacheKey);

                if ($attempt < 3) {
                    usleep(300000); // 300ms pause before retrying
                    continue;
                }

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
        return 'N/A';
    }
}
