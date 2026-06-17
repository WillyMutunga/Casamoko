<?php

namespace App\Modules\Accounts\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Modules\Finance\Models\WalletTransaction;
use App\Modules\Messaging\Models\MessageRecord;
use App\Modules\Accounts\Models\ClientAccount;
use App\Modules\Accounts\Models\ResellerAccount;

class AnalyticsController extends Controller
{
    public function adminDashboard(Request $request)
    {
        // Global Revenue (Sum of all TOPUPs or SMS_DISPATCH absolute values)
        // Let's use SMS_DISPATCH absolute value as "Usage Revenue"
        $globalRevenue = abs(WalletTransaction::where('type', 'SMS_DISPATCH')->sum('amount')) 
                         + abs(WalletTransaction::where('type', 'BULK_CAMPAIGN_DISPATCH')->sum('amount'));
        
        $onboardedResellers = ResellerAccount::count();
        $onboardedClients = ClientAccount::count();
        
        $totalSmsFired = MessageRecord::count();
        
        // Let's just hardcode peak capacity for now since we haven't built the Redis TPS tracker yet
        $peakCapacity = 10240; 

        return response()->json([
            'global_revenue' => round($globalRevenue, 2),
            'onboarded_resellers' => $onboardedResellers,
            'onboarded_clients' => $onboardedClients,
            'total_sms_fired' => $totalSmsFired,
            'peak_capacity_tps' => $peakCapacity
        ]);
    }

    public function getClientMetrics(Request $request)
    {
        $clientAccountId = $request->user()->clientAccount->id ?? null;
        if (!$clientAccountId) {
            return response()->json(['error' => 'No client account'], 403);
        }

        $totalSpent = abs(WalletTransaction::where('client_account_id', $clientAccountId)
            ->whereIn('type', ['SMS_DISPATCH', 'BULK_CAMPAIGN_DISPATCH'])
            ->sum('amount'));

        $totalSms = MessageRecord::whereHas('campaign', function ($query) use ($clientAccountId) {
            $query->where('client_account_id', $clientAccountId);
        })->count();
        
        $deliveredSms = MessageRecord::whereHas('campaign', function ($query) use ($clientAccountId) {
            $query->where('client_account_id', $clientAccountId);
        })->where('status', 'DELIVERED')->count();
        
        $failedSms = MessageRecord::whereHas('campaign', function ($query) use ($clientAccountId) {
            $query->where('client_account_id', $clientAccountId);
        })->where('status', 'FAILED')->count();

        $deliveryRate = $totalSms > 0 ? round(($deliveredSms / $totalSms) * 100, 1) : 100;

        // Active Campaigns
        $activeCampaigns = \App\Modules\Messaging\Models\Campaign::where('client_account_id', $clientAccountId)
            ->whereIn('status', ['PROCESSING', 'SCHEDULED'])
            ->count();

        return response()->json([
            'total_spent' => round($totalSpent, 4),
            'total_sms' => $totalSms,
            'delivery_rate' => $deliveryRate,
            'active_campaigns' => $activeCampaigns,
            'delivered_sms' => $deliveredSms,
            'failed_sms' => $failedSms
        ]);
    }
}
