<?php

namespace App\Modules\Messaging\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Modules\Messaging\Models\Campaign;
use App\Modules\Messaging\Models\MessageRecord;
use App\Modules\Contacts\Models\Contact;
use Carbon\Carbon;
use App\Modules\Messaging\Models\OptOutRegistry;
use App\Modules\Finance\Services\LedgerService;

class DispatchBulkCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // Allow 1 hour for massive campaigns

    protected int $campaignId;
    protected array $targets;
    protected float $singleSmsCost;

    public function __construct(int $campaignId, array $targets, float $singleSmsCost)
    {
        $this->campaignId = $campaignId;
        $this->targets = $targets;
        $this->singleSmsCost = $singleSmsCost;
    }

    public function handle()
    {
        $campaign = Campaign::with('clientAccount')->find($this->campaignId);
        
        if (!$campaign || !in_array($campaign->status, ['PROCESSING', 'SCHEDULED'])) {
            return;
        }

        // If it was SCHEDULED and the job is now executing, transition it to PROCESSING
        if ($campaign->status === 'SCHEDULED') {
            $campaign->update(['status' => 'PROCESSING']);
        }

        $clientAccount = $campaign->clientAccount;
        $isAbTest = $campaign->is_ab_test;
        $ratio = $campaign->ab_split_ratio;

        // Base metrics
        $templateA = $campaign->template;
        $encodingA = \App\Modules\Messaging\Controllers\CampaignController::getUnicodeType($templateA);
        $segmentsA = \App\Modules\Messaging\Controllers\CampaignController::getSegmentCount($templateA, $encodingA);
        
        $templateB = $campaign->template_b;
        $segmentsB = 1;
        if ($isAbTest && $templateB) {
            $encodingB = \App\Modules\Messaging\Controllers\CampaignController::getUnicodeType($templateB);
            $segmentsB = \App\Modules\Messaging\Controllers\CampaignController::getSegmentCount($templateB, $encodingB);
        }

        $sentCount = 0;

        foreach ($this->targets as $index => $phone) {
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (str_starts_with($phone, '0')) {
                $phone = '254' . substr($phone, 1);
            }
            $hash = Contact::hashMsisdn($phone);

            // DND check
            $shouldDefer = false;
            if ($campaign->quiet_hours_start && $campaign->quiet_hours_end) {
                $nowTime = Carbon::now()->format('H:i');
                $start = $campaign->quiet_hours_start;
                $end = $campaign->quiet_hours_end;
                if ($start <= $end) {
                    $shouldDefer = ($nowTime >= $start && $nowTime <= $end);
                } else {
                    $shouldDefer = ($nowTime >= $start || $nowTime <= $end);
                }
            }

            $contact = Contact::updateOrCreate(
                ['client_account_id' => $clientAccount->id, 'msisdn_hash' => $hash],
                ['msisdn' => $phone, 'name' => 'Subscriber ' . ($index + 1)]
            );

            // A/B split logic
            $selectedSegments = $segmentsA;
            if ($isAbTest && $templateB) {
                if (($index % 100) >= $ratio) {
                    $selectedSegments = $segmentsB;
                }
            }

            // DND / Opt-Out Check
            $isOptedOut = OptOutRegistry::where('client_account_id', $clientAccount->id)
                ->where('msisdn_hash', $hash)
                ->exists();

            $recordCost = $selectedSegments * $this->singleSmsCost;

            if ($isOptedOut) {
                // Refund for the message that was paid for upfront
                if ($recordCost > 0) {
                    $ledger = new LedgerService();
                    $ledger->credit(
                        $clientAccount->id,
                        $recordCost,
                        'REFUND',
                        null,
                        null,
                        "Refund for skipped Opt-Out contact: {$phone} (Campaign: {$campaign->id})"
                    );
                }

                MessageRecord::create([
                    'campaign_id' => $campaign->id,
                    'contact_id' => $contact->id,
                    'msisdn_hash' => $hash,
                    'price' => $recordCost,
                    'status' => 'FAILED',
                    'network_status_code' => 'ERR_OPT_OUT',
                ]);
                continue;
            }

            $record = MessageRecord::create([
                'campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'msisdn_hash' => $hash,
                'price' => $recordCost,
                'status' => 'QUEUED',
            ]);

            // We do NOT debit here! We already debited the full $estCost atomically in CampaignController
            if (!$shouldDefer) {
                SendSMSJob::dispatch($record->id);
            }
            $sentCount++;
        }

        $campaign->update([
            'status' => 'COMPLETED',
            'sent_count' => $sentCount, // Note: actually these are queued, but we mark as sent for simplicity. DLR webhook handles FAILED adjustments.
        ]);
    }
}
