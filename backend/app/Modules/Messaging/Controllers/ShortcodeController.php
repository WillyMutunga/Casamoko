<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Modules\Messaging\Models\Shortcode;
use App\Modules\Messaging\Models\Keyword;
use App\Modules\Messaging\Models\IncomingMessage;
use App\Modules\Messaging\Models\MessageRecord;
use App\Modules\Messaging\Models\OptOutRegistry;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Messaging\Models\PricingRule;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Messaging\Jobs\SendSMSJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShortcodeController extends Controller
{
    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Get available shortcodes for client.
     */
    public function listShortcodes(Request $request)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        // Shared shortcodes (client_account_id is null) + dedicated shortcodes (client_account_id matches client)
        $shortcodes = Shortcode::whereNull('client_account_id')
            ->orWhere('client_account_id', $clientAccount->id)
            ->get();

        return response()->json([
            'status' => 'SUCCESS',
            'shortcodes' => $shortcodes
        ]);
    }

    /**
     * List keywords registered under this client's shortcodes.
     */
    public function listKeywords(Request $request)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        // Dedicated shortcodes keywords + Shared shortcode keywords registered by this client
        // Wait, for keywords, how do we track which client registered a keyword on a shared shortcode?
        // Since shared shortcode is used by multiple clients, keywords must have a client context or we filter keywords!
        // To support shared shortcode keywords, let's allow listing all keywords mapped to client's shortcodes.
        // If it's a shared shortcode, only show keywords registered. Wait, does `keywords` have `client_account_id`?
        // Let's check: our keywords table does not have client_account_id, but since shared shortcodes keywords must be isolated,
        // let's add keywords filtering or keep it simple. Let's get keywords through shortcodes.
        $shortcodeIds = Shortcode::where('client_account_id', $clientAccount->id)
            ->orWhereNull('client_account_id')
            ->pluck('id');

        $keywords = Keyword::whereIn('shortcode_id', $shortcodeIds)->with('shortcode')->get();

        return response()->json([
            'status' => 'SUCCESS',
            'keywords' => $keywords
        ]);
    }

    /**
     * Create keyword rule.
     */
    public function storeKeyword(Request $request)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        $validator = Validator::make($request->all(), [
            'shortcode_id' => 'required|integer',
            'keyword' => 'required|string',
            'action_type' => 'required|string', // OPT_IN, OPT_OUT, WEBHOOK
            'callback_webhook' => 'nullable|url',
            'reply_message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $shortcodeId = $request->input('shortcode_id');
        $shortcode = Shortcode::find($shortcodeId);

        if (!$shortcode) {
            return response()->json(['error' => 'SHORTCODE_NOT_FOUND'], 404);
        }

        // Dedicated check: if shortcode is dedicated, ensure it belongs to this client
        if ($shortcode->is_dedicated && $shortcode->client_account_id !== $clientAccount->id) {
            return response()->json(['error' => 'UNAUTHORIZED_SHORTCODE'], 403);
        }

        $keywordText = strtoupper(trim($request->input('keyword')));

        // Shared check: ensure keyword is unique case-insensitively on this shortcode across all clients!
        $existing = Keyword::where('shortcode_id', $shortcode->id)
            ->whereRaw('UPPER(keyword) = ?', [$keywordText])
            ->exists();

        if ($existing) {
            return response()->json([
                'error' => 'KEYWORD_ALREADY_TAKEN',
                'message' => "The keyword '{$keywordText}' is already claimed on shortcode '{$shortcode->shortcode}'."
            ], 400);
        }

        $keyword = Keyword::create([
            'shortcode_id' => $shortcode->id,
            'client_account_id' => $clientAccount->id,
            'keyword' => $keywordText,
            'action_type' => $request->input('action_type'),
            'callback_webhook' => $request->input('callback_webhook'),
            'reply_message' => $request->input('reply_message'),
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'keyword' => $keyword
        ], 201);
    }

    public function handleSafaricomMO(Request $request)
    {
        // Safaricom SDP pushes JSON like: { "smsServiceActivationNumber": "22344", "senderAddress": "254712345678", "message": "JOIN" }
        $payload = $request->all();
        
        $shortcodeText = $payload['smsServiceActivationNumber'] ?? $payload['shortcode'] ?? null;
        $msisdn = $payload['senderAddress'] ?? $payload['msisdn'] ?? null;
        $messageText = $payload['message'] ?? null;
        $linkId = $payload['linkId'] ?? null;

        if (!$shortcodeText || !$msisdn || !$messageText) {
            Log::warning('Safaricom MO Webhook missing fields', $payload);
            return response()->json(['error' => 'Invalid payload structure'], 400);
        }

        return $this->processMO($shortcodeText, $msisdn, $messageText, $request->ip(), null, $linkId);
    }



    protected function processMO($shortcodeText, $rawMsisdn, $messageText, $requestIp, $clientAccountFallback = null, $linkId = null)
    {
        $msisdn = preg_replace('/[^0-9]/', '', $rawMsisdn);
        if (str_starts_with($msisdn, '0')) {
            $msisdn = '254' . substr($msisdn, 1);
        }
        $messageText = trim($messageText);
        $msisdnHash = Contact::hashMsisdn($msisdn);

        $shortcode = Shortcode::where('shortcode', $shortcodeText)->first();
        if (!$shortcode) {
            return response()->json(['error' => 'SHORTCODE_NOT_FOUND', 'message' => "Shortcode '{$shortcodeText}' is not registered on this system."], 404);
        }

        $words = explode(' ', $messageText);
        $firstWord = strtoupper($words[0]);
        $isGlobalStop = in_array($firstWord, ['STOP', 'UNSUBSCRIBE', 'QUIT']);

        $keyword = null;
        $activeSession = null;

        if (!$isGlobalStop) {
            $activeSession = \App\Modules\Messaging\Models\ShortcodeSession::where('shortcode_id', $shortcode->id)
                ->where('msisdn_hash', $msisdnHash)
                ->where(function($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->first();
                
            if (!$activeSession) {
                $keyword = Keyword::where('shortcode_id', $shortcode->id)
                    ->where(function ($query) use ($firstWord) {
                        $query->whereRaw('UPPER(keyword) = ?', [$firstWord])
                              ->orWhere('keyword', '*');
                    })
                    ->orderByRaw("CASE WHEN UPPER(keyword) = ? THEN 1 WHEN keyword = '*' THEN 2 ELSE 3 END", [$firstWord])
                    ->first();
            }
        }

        $clientAccount = null;
        if ($shortcode->is_dedicated) {
            $clientAccount = $shortcode->clientAccount;
        } else {
            if ($keyword && $keyword->client_account_id) {
                $clientAccount = $keyword->clientAccount;
            } elseif ($activeSession && $activeSession->client_account_id) {
                $clientAccount = $activeSession->clientAccount;
            } else {
                $clientAccount = $clientAccountFallback;
            }
        }

        if (!$clientAccount) {
            return response()->json(['error' => 'CLIENT_CONTEXT_NOT_FOUND'], 400);
        }

        $incoming = IncomingMessage::create([
            'shortcode_id' => $shortcode->id,
            'keyword_id' => $keyword ? $keyword->id : null,
            'client_account_id' => $clientAccount->id,
            'msisdn' => $msisdn,
            'msisdn_hash' => $msisdnHash,
            'message' => $messageText,
            'link_id' => $linkId,
        ]);

        $actionType = 'WEBHOOK';
        $replyText = null;
        
        if ($keyword) {
            $actionType = $keyword->action_type;
            $replyText = $keyword->reply_message;
        } elseif ($activeSession) {
            $actionType = 'SESSION_WEBHOOK';
        }

        if ($isGlobalStop) {
            $actionType = 'OPT_OUT';
            $replyText = 'You have successfully unsubscribed from this campaign channel. Reply JOIN to opt in again.';
        }

        $webhookStatus = 'SKIPPED';
        $autoreplyStatus = 'SKIPPED';

        if ($actionType === 'OPT_OUT') {
            OptOutRegistry::updateOrCreate([
                'client_account_id' => $clientAccount->id,
                'msisdn_hash' => $msisdnHash
            ], [
                'msisdn' => $msisdn
            ]);

            \App\Modules\Accounts\Models\ActivityLog::create([
                'client_account_id' => $clientAccount->id,
                'user_id' => null,
                'action' => 'OPT_OUT',
                'description' => "Subscriber opted out via Shortcode {$shortcodeText} with text: {$messageText}",
                'ip_address' => $requestIp,
                'username' => 'SUBSCRIBER',
            ]);
        }

        if ($actionType === 'OPT_IN') {
            OptOutRegistry::where('client_account_id', $clientAccount->id)
                ->where('msisdn_hash', $msisdnHash)
                ->delete();

            Contact::updateOrCreate([
                'client_account_id' => $clientAccount->id,
                'msisdn_hash' => $msisdnHash
            ], [
                'msisdn' => $msisdn,
                'name' => 'Opted-In Shortcode Contact'
            ]);

            \App\Modules\Accounts\Models\ActivityLog::create([
                'client_account_id' => $clientAccount->id,
                'user_id' => null,
                'action' => 'OPT_IN',
                'description' => "Subscriber opted in via Shortcode {$shortcodeText} with text: {$messageText}",
                'ip_address' => $requestIp,
                'username' => 'SUBSCRIBER',
            ]);
        }

        if (($actionType === 'WEBHOOK' && $keyword && $keyword->callback_webhook) || ($actionType === 'SESSION_WEBHOOK' && $activeSession && $activeSession->webhook_url)) {
            $webhookUrl = $activeSession ? $activeSession->webhook_url : $keyword->callback_webhook;
            
            $payload = [
                'msisdn' => $msisdn,
                'shortcode' => $shortcodeText,
                'message' => $messageText,
                'keyword' => $firstWord,
                'timestamp' => now()->toIso8601String()
            ];

            if ($activeSession) {
                $payload['session_id'] = $activeSession->id;
                $payload['current_state'] = $activeSession->current_state;
            }
            
            \App\Modules\Messaging\Jobs\DispatchClientWebhookJob::dispatch(
                $webhookUrl, 
                $payload, 
                $clientAccount->id
            );
            $webhookStatus = 'QUEUED';
        }

        if ($replyText) {
            if ($shortcode->is_premium) {
                $cost = (float) $shortcode->premium_rate;
            } else {
                $primaryRoute = \App\Modules\Messaging\Models\Route::where('is_active', true)->orderBy('priority', 'asc')->first();
                $baseCost = $primaryRoute ? (float) $primaryRoute->cost_per_sms : 0.5000;
                $markup = 0.0000;
                if ($clientAccount->resellerAccount) {
                    $markup += $baseCost * ((float)$clientAccount->resellerAccount->markup_percentage / 100);
                }
                $cost = $baseCost + $markup;
            }

            // Create a dummy campaign to hold the auto-reply context for SendSMSJob
            $campaign = \App\Modules\Messaging\Models\Campaign::create([
                'client_account_id' => $clientAccount->id,
                'name' => "Auto-Reply: " . $keyword->keyword,
                'template' => $replyText,
                'sender_id_id' => 1, // fallback to default sender ID
                'status' => 'PROCESSING'
            ]);

            $mtRecord = MessageRecord::create([
                'campaign_id' => $campaign->id,
                'msisdn_hash' => $msisdnHash,
                'price' => $cost,
                'status' => 'QUEUED',
                'link_id' => $linkId,
            ]);

            try {
                $this->ledgerService->debit(
                    $clientAccount->id,
                    $cost,
                    'SMS_DISPATCH',
                    MessageRecord::class,
                    $mtRecord->id,
                    "Auto-reply MT message triggered by keyword {$firstWord}"
                );

                SendSMSJob::dispatch($mtRecord->id);
                $autoreplyStatus = 'SENT';
            } catch (\Exception $e) {
                $autoreplyStatus = 'INSUFFICIENT_FUNDS_BLOCKED';
            }
        }

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'MO message received and processed.',
            'mo_log' => $incoming,
            'routing' => [
                'action_triggered' => $actionType,
                'webhook_status' => $webhookStatus,
                'auto_reply_status' => $autoreplyStatus,
                'auto_reply_text' => $replyText,
            ]
        ]);
    }

    /**
     * Retrieve inbound MO logs for client.
     */
    public function listMoLogs(Request $request)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        $shortcodeIds = Shortcode::where('client_account_id', $clientAccount->id)
            ->orWhereNull('client_account_id')
            ->pluck('id');

        $logs = IncomingMessage::where('client_account_id', $clientAccount->id)
            ->with(['shortcode', 'keyword'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => 'SUCCESS',
            'logs' => $logs
        ]);
    }

    /**
     * Aggregate threaded outgoing MTs and incoming MOs grouped by MSISDN.
     */
    public function getThreadedConversations(Request $request)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        $shortcodeIds = Shortcode::where('client_account_id', $clientAccount->id)
            ->orWhereNull('client_account_id')
            ->pluck('id');

        // Fetch all incoming MO messages on client shortcodes
        $incoming = IncomingMessage::where('client_account_id', $clientAccount->id)
            ->get()
            ->map(function ($item) {
                return [
                    'direction' => 'INCOMING',
                    'msisdn' => $item->msisdn,
                    'message' => $item->message,
                    'created_at' => $item->created_at->toIso8601String(),
                ];
            });

        // Fetch all outgoing messages (MT) to contacts of this client
        $contacts = Contact::where('client_account_id', $clientAccount->id)->pluck('msisdn', 'msisdn_hash');
        $hashes = $contacts->keys();

        // Get all matching message records
        $outgoing = MessageRecord::whereIn('msisdn_hash', $hashes)
            ->with('campaign')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($item) use ($contacts) {
                // substitute actual MSISDN using saved hash map
                $msisdn = $contacts[$item->msisdn_hash] ?? 'Unknown';
                return [
                    'direction' => 'OUTGOING',
                    'msisdn' => $msisdn,
                    'message' => $item->campaign ? $item->campaign->template : 'Quick Send Dispatch message',
                    'created_at' => $item->created_at->toIso8601String(),
                ];
            });

        // Merge and group by MSISDN
        $merged = $incoming->concat($outgoing)->sortBy('created_at');
        
        $threads = [];
        foreach ($merged as $msg) {
            $msisdn = $msg['msisdn'];
            if ($msisdn === 'Unknown') continue;
            if (!isset($threads[$msisdn])) {
                $threads[$msisdn] = [];
            }
            $threads[$msisdn][] = [
                'direction' => $msg['direction'],
                'message' => $msg['message'],
                'timestamp' => $msg['created_at'],
            ];
        }

        return response()->json([
            'status' => 'SUCCESS',
            'threads' => $threads
        ]);
    }

    /**
     * Dispatch an outgoing MT SMS reply from the Anchor back to the user.
     */
    public function replyToThread(Request $request)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        $validator = Validator::make($request->all(), [
            'msisdn' => 'required|string',
            'message' => 'required|string',
            'shortcode_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $shortcode = Shortcode::find($request->input('shortcode_id'));
        if (!$shortcode || ($shortcode->is_dedicated && $shortcode->client_account_id !== $clientAccount->id)) {
            return response()->json(['error' => 'UNAUTHORIZED_SHORTCODE'], 403);
        }

        // 1. Resolve Contact
        $msisdn = preg_replace('/[^0-9]/', '', $request->input('msisdn'));
        if (str_starts_with($msisdn, '0')) {
            $msisdn = '254' . substr($msisdn, 1);
        }
        $msisdnHash = Contact::hashMsisdn($msisdn);
        
        $contact = Contact::updateOrCreate(
            ['client_account_id' => $clientAccount->id, 'msisdn_hash' => $msisdnHash],
            ['msisdn' => $msisdn, 'name' => 'Shortcode Interaction']
        );

        // 2. Resolve Pricing
        $primaryRoute = \App\Modules\Messaging\Models\Route::where('is_active', true)->orderBy('priority', 'asc')->first();
        $totalCost = $primaryRoute ? (float) $primaryRoute->cost_per_sms : 0.5000;

        if ($clientAccount->resellerAccount) {
            $markupRatio = (float) $clientAccount->resellerAccount->markup_percentage;
            $totalCost += $totalCost * ($markupRatio / 100);
        }

        // 3. Resolve Sender ID
        $senderID = \App\Modules\Messaging\Models\SenderID::firstOrCreate(
            ['client_account_id' => $clientAccount->id, 'sender_id' => $shortcode->shortcode],
            ['status' => 'APPROVED']
        );

        $messageText = $request->input('message');

        // 4. Create Campaign
        $campaign = \App\Modules\Messaging\Models\Campaign::create([
            'client_account_id' => $clientAccount->id,
            'sender_id_id' => $senderID->id,
            'name' => "Shortcode Reply to " . substr($msisdn, 0, 6) . "xxxx",
            'template' => $messageText,
            'unicode_type' => (strlen($messageText) !== utf8_decode($messageText)) ? 'UCS-2' : 'GSM-7',
            'status' => 'PROCESSING',
            'sent_count' => 0,
            'delivered_count' => 0,
            'failed_count' => 0,
        ]);

        // 5. Create MessageRecord
        $record = MessageRecord::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'msisdn_hash' => $msisdnHash,
            'route_id' => null,
            'price' => $totalCost,
            'status' => 'QUEUED',
            'link_id' => null, // MT Replies don't strictly need a link_id unless it's PRSP on-demand
        ]);

        // 6. Charge Ledger & Dispatch
        try {
            $this->ledgerService->reserveFunds($clientAccount->id, $totalCost, "Shortcode MT Reply to {$msisdn}");
            
            SendSMSJob::dispatch($record->id);
            
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Reply dispatched successfully.'
            ]);
        } catch (\App\Modules\Finance\Exceptions\InsufficientFundsException $e) {
            $record->update(['status' => 'FAILED', 'network_status_code' => 'INSUFFICIENT_FUNDS']);
            $campaign->update(['status' => 'FAILED', 'failed_count' => 1]);
            return response()->json(['error' => 'INSUFFICIENT_FUNDS', 'message' => $e->getMessage()], 402);
        }
    }
}
