<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Modules\Messaging\Models\Campaign;
use App\Modules\Messaging\Models\SenderID;
use App\Modules\Messaging\Models\MessageRecord;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Messaging\Models\PricingRule;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Messaging\Jobs\SendSMSJob;
use App\Modules\Messaging\Jobs\DispatchBulkCampaignJob;
use Carbon\Carbon;

class CampaignController extends Controller
{
    protected LedgerService $ledgerService;

    public function __construct(LedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Helper to analyze string encoding.
     */
    public static function getUnicodeType($text)
    {
        $gsm7 = "@£\${}_!\"#%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà^{}\\[~]|€ \r\n\t";
        $len = mb_strlen($text);
        for ($i = 0; $i < $len; $i++) {
            if (mb_strpos($gsm7, mb_substr($text, $i, 1)) === false) {
                return 'UCS-2';
            }
        }
        return 'GSM-7';
    }

    /**
     * Helper to calculate SMS parts/segments.
     */
    public static function getSegmentCount($text, $encoding)
    {
        $len = mb_strlen($text);
        if ($encoding === 'GSM-7') {
            if ($len <= 160) return 1;
            return (int) ceil($len / 153);
        } else {
            if ($len <= 70) return 1;
            return (int) ceil($len / 67);
        }
    }

    /**
     * List all campaigns.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        $campaigns = Campaign::where('client_account_id', $clientAccount->id)
            ->with('senderId')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => 'SUCCESS',
            'campaigns' => $campaigns
        ]);
    }

    /**
     * Create advanced campaign.
     */
    public function store(Request $request)
    {
        try {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        if (!$clientAccount) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 403);
        }

        if ($user->sub_role === 'VIEWER_ONLY') {
            return response()->json(['error' => 'UNAUTHORIZED', 'message' => 'Your account has view-only access. You cannot launch campaigns.'], 403);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'template' => 'required|string',
            'sender_id_id' => 'required|integer',
            'opt_out_message' => 'nullable|string',
            'tps_limit' => 'nullable|integer',
            'target_contacts' => 'nullable|array',
            'quiet_hours_start' => 'nullable|string',
            'quiet_hours_end' => 'nullable|string',
            'recurring_type' => 'nullable|string', // ONCE, DAILY, WEEKLY, MONTHLY
            'scheduled_at' => 'nullable|string',
            'opt_out_message' => 'nullable|string',
            'is_ab_test' => 'nullable|boolean',
            'template_b' => 'nullable|string',
            'ab_split_ratio' => 'nullable|integer',
            'target_contacts' => 'nullable|array', // custom target list numbers
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::error('Campaign Validation Failed: ' . json_encode($validator->errors()));
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate Sender
        $sender = SenderID::where('id', $request->input('sender_id_id'))
            ->where('client_account_id', $clientAccount->id)
            ->first();

        if (!$sender) {
            return response()->json(['error' => 'INVALID_SENDER_ID', 'message' => 'Sender ID not found or unauthorized.'], 400);
        }

        $templateA = $request->input('template');
        $optOutMessage = $request->input('opt_out_message');
        $fullTemplateA = $templateA . ($optOutMessage ? "\n" . trim($optOutMessage) : '');
        $encodingA = self::getUnicodeType($fullTemplateA);
        $segmentsA = self::getSegmentCount($fullTemplateA, $encodingA);

        $isAbTest = $request->input('is_ab_test', false);
        $templateB = $request->input('template_b');
        $encodingB = 'GSM-7';
        $segmentsB = 1;

        if ($isAbTest && $templateB) {
            $fullTemplateB = $templateB . ($optOutMessage ? "\n" . trim($optOutMessage) : '');
            $encodingB = self::getUnicodeType($fullTemplateB);
            $segmentsB = self::getSegmentCount($fullTemplateB, $encodingB);
        }

        // Segment & Unicode Type summary
        $unicodeType = ($encodingA === 'UCS-2' || $encodingB === 'UCS-2') ? 'UCS-2' : 'GSM-7';

        // Targets parsing
        $targets = $request->input('target_contacts') ?: ['254711222333', '254722333444'];
        $totalContacts = count($targets);

        // Dynamic pricing calculation from Primary Route
        $primaryRoute = \App\Modules\Messaging\Models\Route::where('is_active', true)->orderBy('priority', 'asc')->first();
        $baseCost = $primaryRoute ? (float) $primaryRoute->cost_per_sms : 0.5000;
        $markup = 0.0000;

        if ($clientAccount->resellerAccount) {
            $markup += $baseCost * ((float)$clientAccount->resellerAccount->markup_percentage / 100);
        }
        $singleSmsCost = $baseCost + $markup;

        // Total segments estimate
        $estSegments = 0;
        if ($isAbTest) {
            $ratio = $request->input('ab_split_ratio', 50);
            $countA = (int) ceil($totalContacts * ($ratio / 100));
            $countB = $totalContacts - $countA;
            $estSegments = ($countA * $segmentsA) + ($countB * $segmentsB);
        } else {
            $estSegments = $totalContacts * $segmentsA;
        }

        $estCost = $estSegments * $singleSmsCost;

        // Check funds
        $availableFunds = (float)$clientAccount->wallet_balance + (float)$clientAccount->credit_limit;
        if ($availableFunds < $estCost && $request->input('status') !== 'DRAFT') {
            return response()->json([
                'error' => 'OUT_OF_FUNDS',
                'message' => "Insufficient balance to launch this campaign. Estimated cost: \${$estCost}, Available: \${$availableFunds}"
            ], 402);
        }

        // Multi-step approvals: if sub_role is CAMPAIGN_MANAGER, campaigns must be approved by client admin
        $approvalStatus = 'APPROVED';
        if ($user->role_tier === 'CLIENT' && $user->sub_role === 'CAMPAIGN_MANAGER') {
            $approvalStatus = 'PENDING_APPROVAL';
        }

        $status = $request->input('status') ?: 'DRAFT';
        if ($approvalStatus === 'PENDING_APPROVAL' && $status === 'SCHEDULED') {
            // Keep scheduled but set to draft/pending
            $status = 'DRAFT';
        }

        $scheduledAt = $request->input('scheduled_at') ? Carbon::parse($request->input('scheduled_at')) : null;

        $campaign = Campaign::create([
            'client_account_id' => $clientAccount->id,
            'sender_id_id' => $sender->id,
            'name' => $request->input('name'),
            'template' => $templateA,
            'opt_out_message' => $optOutMessage ? trim($optOutMessage) : null,
            'unicode_type' => $unicodeType,
            'tps_limit' => $request->input('tps_limit', 50),
            'quiet_hours_start' => $request->input('quiet_hours_start'),
            'quiet_hours_end' => $request->input('quiet_hours_end'),
            'recurring_type' => $request->input('recurring_type', 'ONCE'),
            'approval_status' => $approvalStatus,
            'is_ab_test' => $isAbTest,
            'template_b' => $templateB,
            'ab_split_ratio' => $request->input('ab_split_ratio', 50),
            'scheduled_at' => $scheduledAt,
            'status' => ($scheduledAt && $scheduledAt->isFuture()) ? 'SCHEDULED' : $status,
        ]);

        // If status is immediate (PROCESSING/SCHEDULED) and approved, dispatch sending!
        if (($status === 'PROCESSING' || $status === 'SCHEDULED') && $approvalStatus === 'APPROVED' && !$scheduledAt) {
            
            // ATOMIC BULK DEDUCTION
            try {
                $this->ledgerService->debit(
                    $clientAccount->id,
                    $estCost,
                    'BULK_CAMPAIGN_DISPATCH',
                    Campaign::class,
                    $campaign->id,
                    "Bulk Campaign Dispatch Reservation: {$campaign->name}"
                );
            } catch (\Exception $e) {
                $campaign->update(['status' => 'FAILED']);
                return response()->json([
                    'error' => 'OUT_OF_FUNDS',
                    'message' => $e->getMessage()
                ], 402);
            }

            if ($campaign->status === 'PROCESSING') {
                DispatchBulkCampaignJob::dispatch($campaign->id, $targets, $singleSmsCost);
            } elseif ($campaign->status === 'SCHEDULED' && $scheduledAt) {
                DispatchBulkCampaignJob::dispatch($campaign->id, $targets, $singleSmsCost)->delay($scheduledAt);
            }
        }

        $gatewayBalance = 'N/A';
        try {
            $gateway = new \App\Modules\Messaging\Services\Gateways\SafaricomSmsGateway();
            $gatewayBalance = $gateway->getBalance() ?? 'N/A';
        } catch (\Exception $e) {
            $gatewayBalance = 'Error: ' . $e->getMessage();
        }

        return response()->json([
            'status' => 'SUCCESS',
            'campaign' => $campaign,
            'gateway_balance' => $gatewayBalance,
            'analysis' => [
                'encoding_a' => $encodingA,
                'segments_a' => $segmentsA,
                'encoding_b' => $isAbTest ? $encodingB : null,
                'segments_b' => $isAbTest ? $segmentsB : null,
                'estimated_cost' => $estCost,
                'estimated_segments' => $estSegments,
            ]
        ], 201);
        } catch (\Exception $ex) {
            \Illuminate\Support\Facades\Log::error('Campaign Store Exception: ' . $ex->getMessage() . ' at ' . $ex->getFile() . ':' . $ex->getLine());
            return response()->json(['error' => 'SERVER_ERROR', 'message' => $ex->getMessage()], 500);
        }
    }

    /**
     * Preview substituted template tags.
     */
    public function preview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template' => 'required|string',
            'contact_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $clientAccount = $user->clientAccount;

        $name = 'Jane Doe';
        $email = 'jane@acme.com';

        if ($request->input('contact_id')) {
            $contact = Contact::where('id', $request->input('contact_id'))
                ->where('client_account_id', $clientAccount->id)
                ->first();
            if ($contact) {
                $name = $contact->name ?: 'Jane Doe';
                $email = $contact->email ?: 'jane@acme.com';
            }
        }

        $template = $request->input('template');
        $substituted = str_replace(
            ['{Name}', '{FirstName}', '{Email}'],
            [$name, explode(' ', $name)[0], $email],
            $template
        );

        return response()->json([
            'status' => 'SUCCESS',
            'substituted_text' => $substituted
        ]);
    }

    /**
     * Action switches: PAUSE, RESUME, CANCEL.
     */
    public function action(Request $request, $id)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        $campaign = Campaign::where('id', $id)
            ->where('client_account_id', $clientAccount->id)
            ->first();

        if (!$campaign) {
            return response()->json(['error' => 'CAMPAIGN_NOT_FOUND'], 404);
        }

        $action = strtoupper($request->input('action'));

        if ($action === 'PAUSE') {
            $campaign->update(['status' => 'PAUSED']);
        } elseif ($action === 'RESUME') {
            $campaign->update(['status' => 'PROCESSING']);
        } elseif ($action === 'CANCEL') {
            $campaign->update(['status' => 'COMPLETED']); // Mark completed/cancelled
        } else {
            return response()->json(['error' => 'INVALID_ACTION'], 400);
        }

        return response()->json([
            'status' => 'SUCCESS',
            'campaign' => $campaign
        ]);
    }

    /**
     * Approve campaign (multi-step workflow).
     */
    public function approve(Request $request, $id)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        // Restricted to CLIENT_ADMIN
        if ($user->role_tier === 'CLIENT' && $user->sub_role !== 'CLIENT_ADMIN') {
            return response()->json(['error' => 'PRIVILEGE_UNAUTHORIZED', 'message' => 'Only Client Admins can approve campaigns.'], 403);
        }

        $campaign = Campaign::where('id', $id)
            ->where('client_account_id', $clientAccount->id)
            ->first();

        if (!$campaign) {
            return response()->json(['error' => 'CAMPAIGN_NOT_FOUND'], 404);
        }

        $campaign->update([
            'approval_status' => 'APPROVED',
            'status' => 'PROCESSING'
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'campaign' => $campaign
        ]);
    }

    /**
     * Duplicate/clone campaign template.
     */
    public function duplicate(Request $request, $id)
    {
        $user = $request->user();
        $clientAccount = $user->clientAccount;

        $original = Campaign::where('id', $id)
            ->where('client_account_id', $clientAccount->id)
            ->first();

        if (!$original) {
            return response()->json(['error' => 'CAMPAIGN_NOT_FOUND'], 404);
        }

        $clone = Campaign::create([
            'client_account_id' => $clientAccount->id,
            'sender_id_id' => $original->sender_id_id,
            'name' => $original->name . ' (Copy)',
            'template' => $original->template,
            'unicode_type' => $original->unicode_type,
            'tps_limit' => $original->tps_limit,
            'quiet_hours_start' => $original->quiet_hours_start,
            'quiet_hours_end' => $original->quiet_hours_end,
            'recurring_type' => $original->recurring_type,
            'is_ab_test' => $original->is_ab_test,
            'template_b' => $original->template_b,
            'ab_split_ratio' => $original->ab_split_ratio,
            'approval_status' => 'APPROVED',
            'status' => 'DRAFT',
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'campaign' => $clone
        ]);
    }

    public function logs(Request $request, $id)
    {
        $clientAccount = $request->user()->clientAccount;
        if (!$clientAccount) {
            return response()->json(['error' => 'Not a client'], 403);
        }

        $campaign = Campaign::where('id', $id)
            ->where('client_account_id', $clientAccount->id)
            ->first();

        if (!$campaign) {
            return response()->json(['error' => 'CAMPAIGN_NOT_FOUND'], 404);
        }

        $status = $request->input('status');
        $query = \App\Modules\Messaging\Models\MessageRecord::where('campaign_id', $campaign->id);
        
        if ($status) {
            $query->where('status', $status);
        }

        $logs = $query->orderBy('id', 'desc')->paginate(50);

        return response()->json([
            'status' => 'SUCCESS',
            'logs' => $logs
        ]);
    }
}
