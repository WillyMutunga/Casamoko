<?php

namespace App\Modules\Messaging\Services\Gateways;

interface SmsGatewayInterface
{
    /**
     * Standardized outbound SMS dispatch method.
     *
     * @param string $senderId Alphanumeric identity (max 11 chars)
     * @param string $msisdn Destination E.164 subscriber number
     * @param string $message UCS-2/GSM-7 message template text
     * @return array Standardized response containing ['status' => 'SENT|FAILED', 'message_id' => '...', 'error_code' => '...']
     */
    public function send(string $senderId, string $msisdn, string $message): array;
}
