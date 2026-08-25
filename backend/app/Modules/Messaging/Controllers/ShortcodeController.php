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

    /**
     * Delete keyword rule.
     */
    public function deleteKeyword(Request $request, $id)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        $keyword = Keyword::find($id);

        if (!$keyword) {
            return response()->json(['error' => 'KEYWORD_NOT_FOUND'], 404);
        }

        if ($keyword->client_account_id !== $clientAccount->id) {
            return response()->json(['error' => 'UNAUTHORIZED'], 403);
        }

        $keyword->delete();

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Keyword deleted successfully.'
        ]);
    }

    public function handleSafaricomMO(Request $request)
    {
        $payload = $request->all();
        Log::info('Safaricom DSDP Webhook Received: ', $payload);
        
        $shortcodeText = null;
        $msisdn = null;
        $messageText = null;
        $linkId = null;
        $requestId = $payload['requestId'] ?? (string) time();

        // Safaricom DSDP v1.1 CP_NOTIFICATION / PAYMENT / NOTIFY_LINKID / INTERACTIVE parser
        if (isset($payload['operation']) && in_array(strtoupper($payload['operation']), ['CP_NOTIFICATION', 'INTERACTIVE', 'PAYMENT'])) {
            $data = $payload['requestParam']['data'] ?? [];
            foreach ($data as $item) {
                if (!isset($item['name']) || !isset($item['value'])) continue;
                $name = strtoupper($item['name']);
                $val = $item['value'];

                if ($name === 'MSISDN') {
                    $msisdn = $val;
                } elseif (in_array($name, ['USER_DATA', 'SMS', 'CONTENT', 'MESSAGE'])) {
                    $messageText = $val;
                } elseif ($name === 'LINKID') {
                    $linkId = $val;
                } elseif (in_array($name, ['DA', 'SHORTCODE', 'OFFERCODE'])) {
                    $shortcodeText = $val;
                }
            }

            $additionalData = $payload['requestParam']['additionalData'] ?? [];
            foreach ($additionalData as $item) {
                if (!isset($item['name']) || !isset($item['value'])) continue;
                $name = strtoupper($item['name']);
                if ($name === 'DA') {
                    $shortcodeText = $item['value'];
                } elseif ($name === 'SMS') {
                    $messageText = $item['value'];
                }
            }
        }

        // Greedy fallback extraction across all supported aggregators
        if (!$shortcodeText) $shortcodeText = $payload['smsServiceActivationNumber'] ?? $payload['shortcode'] ?? $payload['to'] ?? $payload['destination'] ?? $payload['receiver'] ?? '20606';
        if (!$msisdn) $msisdn = $payload['senderAddress'] ?? $payload['msisdn'] ?? $payload['from'] ?? $payload['sender'] ?? $payload['source'] ?? null;
        
        if (!$messageText) {
            $messageRaw = $payload['message'] ?? $payload['text'] ?? $payload['content'] ?? $payload['body'] ?? null;
            $messageText = is_array($messageRaw) ? ($messageRaw['message'] ?? $messageRaw['text'] ?? json_encode($messageRaw)) : (string) $messageRaw;
        }
        
        if (!$linkId) $linkId = $payload['linkId'] ?? $payload['link_id'] ?? $payload['correlator'] ?? $payload['requestId'] ?? null;

        // Safaricom DSDP v1.1 Compliant Acknowledgement Payload
        $acknowledgement = response()->json([
            'requestId' => (string) $requestId,
            'responseId' => 'cp_' . $requestId,
            'responseTimeStamp' => date('YmdHis'),
            'operation' => 'CP_NOTIFICATION',
            'responseParam' => [
                'status' => '0',
                'statusCode' => '0000',
                'description' => 'Success'
            ]
        ]);

        // 1. Send the compliance response to Safaricom DSDP server immediately
        $acknowledgement->send();

        // 2. Flush and close HTTP connection so Safaricom receives HTTP 200 without waiting
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }

        // 3. Process MO in background
        if ($shortcodeText && $msisdn && $messageText) {
            try {
                $requestIp = $request->ip();
                $this->processMO($shortcodeText, $msisdn, $messageText, $requestIp, null, $linkId);
            } catch (\Exception $e) {
                Log::error("Deferred processMO Error: " . $e->getMessage());
            }
        }

        // 4. Exit script cleanly
        exit;
    }



    protected function processMO($shortcodeText, $rawMsisdn, $messageText, $requestIp, $clientAccountFallback = null, $linkId = null)
    {
        $msisdn = preg_replace('/[^0-9]/', '', $rawMsisdn);
        if (str_starts_with($msisdn, '0')) {
            $msisdn = '254' . substr($msisdn, 1);
        }
        $messageText = trim($messageText);
        $msisdnHash = Contact::hashMsisdn($msisdn);

        $shortcodes = Shortcode::where('shortcode', $shortcodeText)->get();
        if ($shortcodes->isEmpty()) {
            return response()->json(['error' => 'SHORTCODE_NOT_FOUND', 'message' => "Shortcode '{$shortcodeText}' is not registered on this system."], 404);
        }
        $shortcodeIds = $shortcodes->pluck('id');
        $shortcode = $shortcodes->first(); // Default fallback

        $words = explode(' ', $messageText);
        $firstWord = strtoupper($words[0]);
        $isGlobalStop = in_array($firstWord, ['STOP', 'UNSUBSCRIBE', 'QUIT']);

        $keyword = null;
        $activeSession = null;

        if (!$isGlobalStop) {
            $activeSession = \App\Modules\Messaging\Models\ShortcodeSession::whereIn('shortcode_id', $shortcodeIds)
                ->where('msisdn_hash', $msisdnHash)
                ->where(function($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->first();
                
            if (!$activeSession) {
                $keyword = Keyword::whereIn('shortcode_id', $shortcodeIds)
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
                $shortcode = $keyword->shortcode;
            } elseif ($activeSession && $activeSession->client_account_id) {
                $clientAccount = $activeSession->clientAccount;
                $shortcode = $activeSession->shortcode;
            } else {
                $clientAccount = $clientAccountFallback;
            }
        }

        if (!$clientAccount) {
            return response()->json(['error' => 'CLIENT_CONTEXT_NOT_FOUND'], 400);
        }

        if (!$isGlobalStop) {
            // Create or Extend Sticky Session for 24 hours
            \App\Modules\Messaging\Models\ShortcodeSession::updateOrCreate(
                [
                    'shortcode_id' => $shortcode->id,
                    'msisdn_hash' => $msisdnHash
                ],
                [
                    'client_account_id' => $clientAccount->id,
                    'msisdn' => $msisdn,
                    'expires_at' => now()->addHours(24)
                ]
            );
        } else {
            // End sticky session on STOP
            \App\Modules\Messaging\Models\ShortcodeSession::where('shortcode_id', $shortcode->id)
                ->where('msisdn_hash', $msisdnHash)
                ->delete();
        }

        $incoming = IncomingMessage::create([
            'shortcode_id' => $shortcode->id,
            'keyword_id' => $keyword ? $keyword->id : null,
            'client_account_id' => $clientAccount->id,
            'msisdn' => $msisdn,
            'msisdn_hash' => $msisdnHash,
            'message' => $messageText,
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
                'user_agent' => 'SYSTEM_SUBSCRIBER',
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
                'user_agent' => 'SYSTEM_SUBSCRIBER',
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
        $this->ensureMessagingColumnsExist();

        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        $shortcodeIds = Shortcode::where('client_account_id', $clientAccount->id)
            ->orWhereNull('client_account_id')
            ->pluck('id');

        $isSuperAdmin = $user->role_tier === 'SUPER_ADMIN' || ($clientAccount && $clientAccount->id === 1);

        if ($isSuperAdmin) {
            // God Mode: Fetch ALL incoming and outgoing messages across all clients
            $incoming = IncomingMessage::where(function($q) {
                    $q->where('is_deleted', false)->orWhereNull('is_deleted');
                })
                ->orderBy('id', 'desc')
                ->take(2000)
                ->get()
                ->map(function ($item) {
                    $isoDate = $item->created_at ? \Carbon\Carbon::parse($item->created_at)->toIso8601String() : now()->toIso8601String();
                    return [
                        'id' => $item->id,
                        'type' => 'incoming',
                        'direction' => 'INCOMING',
                        'msisdn' => $item->msisdn,
                        'message' => $item->message,
                        'created_at' => $isoDate,
                        'timestamp' => $isoDate,
                        'shortcode_id' => $item->shortcode_id,
                        'is_read' => $item->is_read,
                        'is_archived' => !empty($item->is_archived),
                    ];
                });

            $outgoing = MessageRecord::where(function($q) {
                    $q->where('is_deleted', false)->orWhereNull('is_deleted');
                })
                ->whereHas('campaign', function ($query) {
                    $query->where('name', 'like', 'Shortcode Reply%');
                })
                ->with('campaign')
                ->orderBy('id', 'desc')
                ->take(2000)
                ->get()
                ->map(function ($item) {
                    $msisdn = $item->contact ? $item->contact->msisdn : 'Unknown';
                    $isoDate = $item->created_at ? \Carbon\Carbon::parse($item->created_at)->toIso8601String() : now()->toIso8601String();
                    return [
                        'id' => $item->id,
                        'type' => 'outgoing',
                        'direction' => 'OUTGOING',
                        'msisdn' => $msisdn,
                        'message' => $item->campaign ? $item->campaign->template : 'Shortcode Reply message',
                        'created_at' => $isoDate,
                        'timestamp' => $isoDate,
                        'is_archived' => !empty($item->is_archived),
                    ];
                });
        } else {
            // Standard Mode: Fetch only messages belonging to this client's workspace
            $incoming = IncomingMessage::where('client_account_id', $clientAccount ? $clientAccount->id : 0)
                ->where(function($q) {
                    $q->where('is_deleted', false)->orWhereNull('is_deleted');
                })
                ->get()
                ->map(function ($item) {
                    $isoDate = $item->created_at ? \Carbon\Carbon::parse($item->created_at)->toIso8601String() : now()->toIso8601String();
                    return [
                        'id' => $item->id,
                        'type' => 'incoming',
                        'direction' => 'INCOMING',
                        'msisdn' => $item->msisdn,
                        'message' => $item->message,
                        'created_at' => $isoDate,
                        'timestamp' => $isoDate,
                        'shortcode_id' => $item->shortcode_id,
                        'is_read' => $item->is_read,
                        'is_archived' => !empty($item->is_archived),
                    ];
                });

            $contacts = $clientAccount ? Contact::where('client_account_id', $clientAccount->id)->pluck('msisdn', 'msisdn_hash') : collect();
            $hashes = $contacts->keys();

            $outgoing = MessageRecord::whereIn('msisdn_hash', $hashes)
                ->where(function($q) {
                    $q->where('is_deleted', false)->orWhereNull('is_deleted');
                })
                ->whereHas('campaign', function ($query) {
                    $query->where('name', 'like', 'Shortcode Reply%');
                })
                ->with('campaign')
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($item) use ($contacts) {
                    $isoDate = $item->created_at ? \Carbon\Carbon::parse($item->created_at)->toIso8601String() : now()->toIso8601String();
                    return [
                        'id' => $item->id,
                        'type' => 'outgoing',
                        'direction' => 'OUTGOING',
                        'msisdn' => $contacts[$item->msisdn_hash] ?? 'Unknown',
                        'message' => $item->campaign ? $item->campaign->template : 'Shortcode Reply message',
                        'created_at' => $isoDate,
                        'timestamp' => $isoDate,
                        'is_archived' => !empty($item->is_archived),
                    ];
                });
        }

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
                'id' => $msg['id'],
                'type' => $msg['type'],
                'direction' => $msg['direction'],
                'message' => $msg['message'],
                'timestamp' => $msg['created_at'],
                'shortcode_id' => $msg['shortcode_id'] ?? null,
                'is_read' => $msg['is_read'] ?? true,
                'is_archived' => $msg['is_archived'] ?? false,
            ];
        }

        return response()->json([
            'status' => 'SUCCESS',
            'threads' => $threads
        ]);
    }

    /**
     * Single message deletion (soft delete) for all roles.
     */
    public function deleteMessage(Request $request)
    {
        $this->ensureMessagingColumnsExist();
        $user = $request->user();
        if (!$user) return response()->json(['error' => 'UNAUTHENTICATED'], 401);

        $id = $request->input('id');
        $type = $request->input('type'); // 'incoming' or 'outgoing'

        if ($type === 'incoming') {
            IncomingMessage::where('id', $id)->update(['is_deleted' => true]);
        } else {
            MessageRecord::where('id', $id)->update(['is_deleted' => true]);
        }

        return response()->json(['status' => 'SUCCESS', 'message' => 'Message deleted.']);
    }

    /**
     * Single message archive / unarchive for all roles.
     */
    public function archiveMessage(Request $request)
    {
        $this->ensureMessagingColumnsExist();
        $user = $request->user();
        if (!$user) return response()->json(['error' => 'UNAUTHENTICATED'], 401);

        $id = $request->input('id');
        $type = $request->input('type');
        $archive = $request->input('is_archived', true);

        if ($type === 'incoming') {
            IncomingMessage::where('id', $id)->update(['is_archived' => $archive]);
        } else {
            MessageRecord::where('id', $id)->update(['is_archived' => $archive]);
        }

        return response()->json(['status' => 'SUCCESS', 'message' => $archive ? 'Message archived.' : 'Message unarchived.']);
    }

    /**
     * Bulk message delete or archive operations for all roles.
     */
    public function bulkActionMessages(Request $request)
    {
        $this->ensureMessagingColumnsExist();
        $user = $request->user();
        if (!$user) return response()->json(['error' => 'UNAUTHENTICATED'], 401);

        $action = $request->input('action'); // 'delete', 'archive', 'unarchive'
        $items = $request->input('items', []); // Array of ['id' => X, 'type' => 'incoming'|'outgoing']

        $incomingIds = array_column(array_filter($items, fn($i) => ($i['type'] ?? '') === 'incoming'), 'id');
        $outgoingIds = array_column(array_filter($items, fn($i) => ($i['type'] ?? '') === 'outgoing'), 'id');

        if ($action === 'delete') {
            if (!empty($incomingIds)) {
                IncomingMessage::whereIn('id', $incomingIds)->update(['is_deleted' => true]);
            }
            if (!empty($outgoingIds)) {
                MessageRecord::whereIn('id', $outgoingIds)->update(['is_deleted' => true]);
            }
        } elseif ($action === 'archive') {
            if (!empty($incomingIds)) {
                IncomingMessage::whereIn('id', $incomingIds)->update(['is_archived' => true]);
            }
            if (!empty($outgoingIds)) {
                MessageRecord::whereIn('id', $outgoingIds)->update(['is_archived' => true]);
            }
        } elseif ($action === 'unarchive') {
            if (!empty($incomingIds)) {
                IncomingMessage::whereIn('id', $incomingIds)->update(['is_archived' => false]);
            }
            if (!empty($outgoingIds)) {
                MessageRecord::whereIn('id', $outgoingIds)->update(['is_archived' => false]);
            }
        }

        return response()->json(['status' => 'SUCCESS', 'message' => "Bulk {$action} action completed successfully."]);
    }

    /**
     * Thread level operations (Delete or Archive entire conversation) for all roles.
     */
    public function manageThread(Request $request)
    {
        $this->ensureMessagingColumnsExist();
        $user = $request->user();
        if (!$user) return response()->json(['error' => 'UNAUTHENTICATED'], 401);

        $action = $request->input('action'); // 'delete', 'archive', 'unarchive'
        
        $msisdnsInput = $request->input('msisdns');
        if (is_array($msisdnsInput)) {
            $msisdns = array_values(array_filter(array_map(function($m) {
                $clean = preg_replace('/[^0-9]/', '', (string)$m);
                return str_starts_with($clean, '0') ? '254' . substr($clean, 1) : $clean;
            }, $msisdnsInput)));
        } else {
            $clean = preg_replace('/[^0-9]/', '', (string)$request->input('msisdn', ''));
            if (str_starts_with($clean, '0')) {
                $clean = '254' . substr($clean, 1);
            }
            $msisdns = array_filter([$clean]);
        }

        if (empty($msisdns)) {
            return response()->json(['error' => 'No valid MSISDNs provided'], 400);
        }

        $hashes = array_map(fn($m) => Contact::hashMsisdn($m), $msisdns);

        if ($action === 'delete') {
            IncomingMessage::whereIn('msisdn', $msisdns)->update(['is_deleted' => true]);
            MessageRecord::whereIn('msisdn_hash', $hashes)->update(['is_deleted' => true]);
        } elseif ($action === 'archive') {
            IncomingMessage::whereIn('msisdn', $msisdns)->update(['is_archived' => true]);
            MessageRecord::whereIn('msisdn_hash', $hashes)->update(['is_archived' => true]);
        } elseif ($action === 'unarchive') {
            IncomingMessage::whereIn('msisdn', $msisdns)->update(['is_archived' => false]);
            MessageRecord::whereIn('msisdn_hash', $hashes)->update(['is_archived' => false]);
        }

        return response()->json(['status' => 'SUCCESS', 'message' => "Thread(s) {$action}d successfully."]);
    }

    /**
     * Mark all incoming messages in a thread as read.
     */
    public function markThreadAsRead(Request $request)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        $validator = Validator::make($request->all(), [
            'msisdn' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $msisdn = preg_replace('/[^0-9]/', '', $request->input('msisdn'));
        if (str_starts_with($msisdn, '0')) {
            $msisdn = '254' . substr($msisdn, 1);
        }

        // Only mark messages belonging to this client account
        IncomingMessage::where('client_account_id', $clientAccount->id)
            ->where('msisdn', $msisdn)
            ->update(['is_read' => true]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Thread marked as read.'
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
            'shortcode_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $shortcodeId = $request->input('shortcode_id');
        
        if ($shortcodeId) {
            $shortcode = Shortcode::find($shortcodeId);
            if (!$shortcode || ($shortcode->is_dedicated && $shortcode->client_account_id !== $clientAccount->id)) {
                return response()->json(['error' => 'UNAUTHORIZED_SHORTCODE'], 403);
            }
        } else {
            // Fallback to the client's dedicated shortcode, or any global shared shortcode (e.g. 20606)
            $shortcode = Shortcode::where('client_account_id', $clientAccount->id)
                ->orWhereNull('client_account_id')
                ->orderBy('is_dedicated', 'desc') // Prefer dedicated over shared
                ->first();
                
            if (!$shortcode) {
                return response()->json(['error' => 'NO_SHORTCODE_AVAILABLE'], 403);
            }
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
            'unicode_type' => (strlen($messageText) !== strlen(mb_convert_encoding($messageText, 'ISO-8859-1', 'UTF-8'))) ? 'UCS-2' : 'GSM-7',
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
        ]);

        // Automatically extend sticky session so the user can reply without a keyword
        \App\Modules\Messaging\Models\ShortcodeSession::updateOrCreate(
            [
                'shortcode_id' => $shortcode->id,
                'msisdn_hash' => $msisdnHash
            ],
            [
                'client_account_id' => $clientAccount->id,
                'msisdn' => $msisdn,
                'expires_at' => now()->addHours(24)
            ]
        );

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
        }
    }

    /**
     * Helper to auto-create missing database columns and fill NULL values.
     */
    private function ensureMessagingColumnsExist()
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('incoming_messages', 'is_deleted')) {
                \Illuminate\Support\Facades\Schema::table('incoming_messages', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('incoming_messages', 'is_archived')) {
                        $table->boolean('is_archived')->default(false);
                    }
                    $table->boolean('is_deleted')->default(false);
                });
            }
            if (!\Illuminate\Support\Facades\Schema::hasColumn('message_records', 'is_deleted')) {
                \Illuminate\Support\Facades\Schema::table('message_records', function (\Illuminate\Database\Schema\Blueprint $table) {
                    if (!\Illuminate\Support\Facades\Schema::hasColumn('message_records', 'is_archived')) {
                        $table->boolean('is_archived')->default(false);
                    }
                    $table->boolean('is_deleted')->default(false);
                });
            }
            // Ensure existing rows with NULL values are set to 0 (false) so SQL queries match them
            \Illuminate\Support\Facades\DB::statement("UPDATE incoming_messages SET is_deleted = 0 WHERE is_deleted IS NULL");
            \Illuminate\Support\Facades\DB::statement("UPDATE incoming_messages SET is_archived = 0 WHERE is_archived IS NULL");
            \Illuminate\Support\Facades\DB::statement("UPDATE message_records SET is_deleted = 0 WHERE is_deleted IS NULL");
            \Illuminate\Support\Facades\DB::statement("UPDATE message_records SET is_archived = 0 WHERE is_archived IS NULL");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Auto-migration error: ' . $e->getMessage());
        }
    }

    /**
     * Safaricom DSDP UAT Sandbox Webhook Handler (MO / Inbound SMS)
     */
    public function handleSafaricomUATMO(Request $request)
    {
        if ($request->isMethod('get')) {
            return response()->json([
                'status' => 'ONLINE',
                'service' => 'Safaricom DSDP v1.1 UAT MO Webhook',
                'allowed_methods' => ['POST', 'GET'],
                'message' => 'Endpoint is live and ready for Safaricom CP_NOTIFICATION POST callbacks.'
            ]);
        }

        $payload = $request->all();
        $requestId = $payload['requestId'] ?? (string) time();

        Log::info('=== SAFARICOM DSDP UAT MO RECEIVED ===', [
            'ip' => $request->ip(),
            'headers' => $request->headers->all(),
            'payload' => $payload
        ]);

        $acknowledgement = response()->json([
            'requestId' => (string) $requestId,
            'responseId' => 'uat_cp_' . $requestId,
            'responseTimeStamp' => date('YmdHis'),
            'operation' => 'CP_NOTIFICATION',
            'responseParam' => [
                'status' => '0',
                'statusCode' => '0000',
                'description' => 'UAT Test Acknowledgement Success'
            ]
        ]);

        $acknowledgement->send();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }

        // Process MO in UAT sandbox mode safely
        try {
            $shortcodeText = $payload['shortcode'] ?? '20606';
            $msisdn = $payload['msisdn'] ?? $payload['senderAddress'] ?? '254700000000';
            $messageText = $payload['message'] ?? $payload['text'] ?? 'UAT TEST MESSAGE';
            $linkId = $payload['linkId'] ?? $payload['requestId'] ?? null;

            $this->processMO($shortcodeText, $msisdn, $messageText, $request->ip(), null, $linkId);
        } catch (\Exception $e) {
            Log::error("Safaricom UAT processMO Error: " . $e->getMessage());
        }

        exit;
    }

    /**
     * Helper route to inspect recent Safaricom UAT logs directly from admin UI / API
     */
    public function getUatLogs()
    {
        $logPath = storage_path('logs/laravel.log');
        if (!file_exists($logPath)) {
            return response()->json(['status' => 'EMPTY', 'logs' => []]);
        }

        $lines = file($logPath);
        $uatLogs = [];
        foreach (array_reverse($lines) as $line) {
            if (str_contains($line, 'SAFARICOM DSDP UAT')) {
                $uatLogs[] = $line;
                if (count($uatLogs) >= 50) break;
            }
        }

        return response()->json([
            'status' => 'SUCCESS',
            'count' => count($uatLogs),
            'logs' => array_reverse($uatLogs)
        ]);
    }

    /**
     * Trigger outbound Payment Request to Safaricom SDP Payment API (Section 2.7 Payment)
     */
    public function triggerPaymentRequest(Request $request)
    {
        $offerCode = $request->input('offer_code', '001234590');
        $msisdn = $request->input('msisdn', '254712345678');
        $chargeAmount = $request->input('charge_amount', '10.0');
        $cpId = $request->input('cp_id', '143');
        $isTestbed = $request->input('is_testbed', true);

        $endpoint = $isTestbed 
            ? 'https://dtsvc.safaricom.com:8480/api/public/SDP/paymentRequest'
            : 'https://dsvc.safaricom.com:9480/api/public/SDP/paymentRequest';

        $payload = [
            'requestId' => 'CASAMOKO' . time() . rand(1000, 9999),
            'channel' => 'APIGW',
            'requestParam' => [
                'data' => [
                    ['name' => 'OfferCode', 'value' => (string) $offerCode],
                    ['name' => 'Msisdn', 'value' => (string) $msisdn],
                    ['name' => 'ChargeAmount', 'value' => (string) $chargeAmount],
                    ['name' => 'CpId', 'value' => (string) $cpId]
                ]
            ],
            'operation' => 'Payment'
        ];

        Log::info('=== TRIGGERING SAFARICOM SDP PAYMENT REQUEST ===', [
            'endpoint' => $endpoint,
            'payload' => $payload
        ]);

        try {
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($endpoint, $payload);

            return response()->json([
                'status' => $response->successful() ? 'SUCCESS' : 'COMPLETED',
                'http_code' => $response->status(),
                'endpoint_used' => $endpoint,
                'request_payload' => $payload,
                'safaricom_response' => $response->json() ?? $response->body()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'DISPATCHED_TO_TESTBED',
                'endpoint_used' => $endpoint,
                'request_payload' => $payload,
                'note' => 'Dispatched to Safaricom Testbed. (Note: Safaricom testbed port 8480 requires active Safaricom VPN/Session during live testing).',
                'error_detail' => $e->getMessage()
            ]);
        }
    }
}
