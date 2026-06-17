<?php
$content = file_get_contents('backend/app/Modules/Messaging/Controllers/ShortcodeController.php');

$newCode = <<<EOT
    public function handleSafaricomMO(Request \$request)
    {
        // Safaricom SDP pushes JSON like: { "smsServiceActivationNumber": "22344", "senderAddress": "254712345678", "message": "JOIN" }
        \$payload = \$request->all();
        
        \$shortcodeText = \$payload['smsServiceActivationNumber'] ?? \$payload['shortcode'] ?? null;
        \$msisdn = \$payload['senderAddress'] ?? \$payload['msisdn'] ?? null;
        \$messageText = \$payload['message'] ?? null;

        if (!\$shortcodeText || !\$msisdn || !\$messageText) {
            Log::warning('Safaricom MO Webhook missing fields', \$payload);
            return response()->json(['error' => 'Invalid payload structure'], 400);
        }

        return \$this->processMO(\$shortcodeText, \$msisdn, \$messageText, \$request->ip(), null);
    }

    public function receiveMO(Request \$request)
    {
        \$validator = Validator::make(\$request->all(), [
            'shortcode' => 'required|string',
            'msisdn' => 'required|string',
            'message' => 'required|string',
        ]);

        if (\$validator->fails()) {
            return response()->json(['errors' => \$validator->errors()], 422);
        }

        return \$this->processMO(
            \$request->input('shortcode'),
            \$request->input('msisdn'),
            \$request->input('message'),
            \$request->ip(),
            \$request->user()->clientAccount ?? null
        );
    }

    protected function processMO(\$shortcodeText, \$rawMsisdn, \$messageText, \$requestIp, \$clientAccountFallback = null)
    {
        \$msisdn = preg_replace('/[^0-9]/', '', \$rawMsisdn);
        if (str_starts_with(\$msisdn, '0')) {
            \$msisdn = '254' . substr(\$msisdn, 1);
        }
        \$messageText = trim(\$messageText);
        \$msisdnHash = Contact::hashMsisdn(\$msisdn);

        \$shortcode = Shortcode::where('shortcode', \$shortcodeText)->first();
        if (!\$shortcode) {
            return response()->json(['error' => 'SHORTCODE_NOT_FOUND', 'message' => "Shortcode '{\$shortcodeText}' is not registered on this system."], 404);
        }

        \$words = explode(' ', \$messageText);
        \$firstWord = strtoupper(\$words[0]);
        \$isGlobalStop = in_array(\$firstWord, ['STOP', 'UNSUBSCRIBE', 'QUIT']);

        \$keyword = Keyword::where('shortcode_id', \$shortcode->id)
            ->where(function (\$query) use (\$firstWord) {
                \$query->whereRaw('UPPER(keyword) = ?', [\$firstWord])
                      ->orWhere('keyword', '*');
            })
            ->orderByRaw("CASE WHEN UPPER(keyword) = ? THEN 1 WHEN keyword = '*' THEN 2 ELSE 3 END", [\$firstWord])
            ->first();

        \$clientAccount = null;
        if (\$shortcode->is_dedicated) {
            \$clientAccount = \$shortcode->clientAccount;
        } else {
            \$clientAccount = \$clientAccountFallback;
        }

        if (!\$clientAccount) {
            return response()->json(['error' => 'CLIENT_CONTEXT_NOT_FOUND'], 400);
        }

        \$incoming = IncomingMessage::create([
            'shortcode_id' => \$shortcode->id,
            'keyword_id' => \$keyword ? \$keyword->id : null,
            'msisdn' => \$msisdn,
            'msisdn_hash' => \$msisdnHash,
            'message' => \$messageText,
        ]);

        \$actionType = \$keyword ? \$keyword->action_type : 'WEBHOOK';
        \$replyText = \$keyword ? \$keyword->reply_message : null;

        if (\$isGlobalStop) {
            \$actionType = 'OPT_OUT';
            \$replyText = 'You have successfully unsubscribed. Reply JOIN to opt in again.';
        }

        \$webhookStatus = 'SKIPPED';
        \$autoreplyStatus = 'SKIPPED';

        if (\$actionType === 'OPT_OUT') {
            OptOutRegistry::updateOrCreate([
                'client_account_id' => \$clientAccount->id,
                'msisdn_hash' => \$msisdnHash
            ], [
                'msisdn' => \$msisdn
            ]);

            \App\Modules\Accounts\Models\ActivityLog::create([
                'client_account_id' => \$clientAccount->id,
                'user_id' => null,
                'action' => 'OPT_OUT',
                'description' => "Subscriber opted out via Shortcode {\$shortcodeText}",
                'ip_address' => \$requestIp,
                'username' => 'SUBSCRIBER',
            ]);
        }

        if (\$actionType === 'OPT_IN') {
            OptOutRegistry::where('client_account_id', \$clientAccount->id)
                ->where('msisdn_hash', \$msisdnHash)
                ->delete();

            Contact::updateOrCreate([
                'client_account_id' => \$clientAccount->id,
                'msisdn_hash' => \$msisdnHash
            ], [
                'msisdn' => \$msisdn,
                'name' => 'Opted-In Shortcode Contact'
            ]);

            \App\Modules\Accounts\Models\ActivityLog::create([
                'client_account_id' => \$clientAccount->id,
                'user_id' => null,
                'action' => 'OPT_IN',
                'description' => "Subscriber opted in via Shortcode {\$shortcodeText}",
                'ip_address' => \$requestIp,
                'username' => 'SUBSCRIBER',
            ]);
        }

        if (\$actionType === 'WEBHOOK' && \$keyword && \$keyword->callback_webhook) {
            try {
                \$response = Http::timeout(2)->post(\$keyword->callback_webhook, [
                    'msisdn' => \$msisdn,
                    'shortcode' => \$shortcodeText,
                    'message' => \$messageText,
                    'keyword' => \$firstWord,
                    'timestamp' => now()->toIso8601String()
                ]);
                \$webhookStatus = \$response->successful() ? 'DELIVERED' : 'FAILED_HTTP_' . \$response->status();
            } catch (\Exception \$e) {
                \$webhookStatus = 'WEBHOOK_TIMEOUT_ERROR';
            }
        }

        if (\$replyText) {
            \$pricingRule = PricingRule::whereNull('client_account_id')->first();
            \$baseCost = \$pricingRule ? (float)\$pricingRule->base_cost : 0.0200;
            \$markup = \$pricingRule ? (float)\$pricingRule->reseller_markup : 0.0050;
            if (\$clientAccount->resellerAccount) {
                \$markup += \$baseCost * ((float)\$clientAccount->resellerAccount->markup_percentage / 100);
            }
            \$cost = \$baseCost + \$markup;

            \$mtRecord = MessageRecord::create([
                'client_account_id' => \$clientAccount->id,
                'msisdn_hash' => \$msisdnHash,
                'price' => \$cost,
                'status' => 'QUEUED',
            ]);

            try {
                \$this->ledgerService->debit(
                    \$clientAccount->id,
                    \$cost,
                    'SMS_DISPATCH',
                    MessageRecord::class,
                    \$mtRecord->id,
                    "Auto-reply MT message triggered by keyword {\$firstWord}"
                );

                \$mtRecord->update(['provider_message_id' => 'REPLY:' . \$replyText]);
                SendSMSJob::dispatch(\$mtRecord->id);
                \$autoreplyStatus = 'SENT';
            } catch (\Exception \$e) {
                \$autoreplyStatus = 'INSUFFICIENT_FUNDS_BLOCKED';
            }
        }

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'MO message received and processed.',
            'mo_log' => \$incoming,
            'routing' => [
                'action_triggered' => \$actionType,
                'webhook_status' => \$webhookStatus,
                'auto_reply_status' => \$autoreplyStatus,
                'auto_reply_text' => \$replyText,
            ]
        ]);
    }

    /**
     * Retrieve inbound MO logs for client.
EOT;

\$startStr = '    public function receiveMO(Request $request)';
\$endStr = '    /**' . PHP_EOL . '     * Retrieve inbound MO logs for client.';

\$startPos = strpos(\$content, \$startStr);
\$endPos = strpos(\$content, \$endStr);

if (\$startPos !== false && \$endPos !== false) {
    \$newContent = substr(\$content, 0, \$startPos) . \$newCode . substr(\$content, \$endPos + strlen(\$endStr));
    file_put_contents('backend/app/Modules/Messaging/Controllers/ShortcodeController.php', \$newContent);
    echo "Refactoring successful!\n";
} else {
    echo "Failed to find start or end positions.\n";
}
?>
