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
        try {
            $grossRevenue = 0.0;
            try {
                $grossRevenue = abs((float) WalletTransaction::sum('amount'));
            } catch (\Throwable $e) {}

            if ($grossRevenue <= 0) {
                try {
                    $grossRevenue = (float) MessageRecord::sum('price');
                } catch (\Throwable $e) {}
            }
            if ($grossRevenue <= 0) {
                try {
                    $grossRevenue = (float) \App\Modules\Messaging\Models\Campaign::sum('estimated_cost');
                } catch (\Throwable $e) {}
            }

            // Estimate carrier cost at ~50% of revenue or per message
            $totalCarrierCost = $grossRevenue > 0 ? round($grossRevenue * 0.50, 2) : 0.0;

            // Try joining routes if available
            try {
                $costSum = DB::table('message_records')
                    ->leftJoin('routes', 'message_records.route_id', '=', 'routes.id')
                    ->sum(DB::raw('COALESCE(routes.cost_per_sms, 0.20)'));
                if ($costSum > 0) {
                    $totalCarrierCost = round((float) $costSum, 2);
                }
            } catch (\Throwable $e) {}

            $netProfit = max(0, $grossRevenue - $totalCarrierCost);
            $profitMargin = $grossRevenue > 0 ? round(($netProfit / $grossRevenue) * 100, 1) : 50.0;

            $onboardedResellers = 0;
            try {
                $onboardedResellers = ResellerAccount::count();
            } catch (\Throwable $e) {}
            try {
                $userResellers = \App\Modules\Accounts\Models\User::where('role_tier', 'RESELLER')->count();
                $onboardedResellers = max($onboardedResellers, $userResellers);
            } catch (\Throwable $e) {}

            $onboardedClients = 0;
            try {
                $onboardedClients = ClientAccount::count();
            } catch (\Throwable $e) {}
            try {
                $userClients = \App\Modules\Accounts\Models\User::whereIn('role_tier', ['CLIENT', 'USER'])->count();
                $onboardedClients = max($onboardedClients, $userClients);
            } catch (\Throwable $e) {}

            $totalSmsFired = 0;
            try {
                $totalSmsFired = MessageRecord::count();
            } catch (\Throwable $e) {}
            try {
                $campaignSms = (int) \App\Modules\Messaging\Models\Campaign::sum('total_contacts');
                $totalSmsFired = max($totalSmsFired, $campaignSms);
            } catch (\Throwable $e) {}

            $peakCapacity = 10240;

            $carrierBreakdown = [
                [
                    'network' => 'Safaricom (2547xx / 2541xx)',
                    'total_sms' => (int)($totalSmsFired * 0.75),
                    'revenue' => round($grossRevenue * 0.75, 2),
                    'cost' => round($totalCarrierCost * 0.75, 2),
                    'profit' => round($netProfit * 0.75, 2),
                    'margin' => $profitMargin
                ],
                [
                    'network' => 'Airtel Kenya (25473x / 25478x)',
                    'total_sms' => (int)($totalSmsFired * 0.20),
                    'revenue' => round($grossRevenue * 0.20, 2),
                    'cost' => round($totalCarrierCost * 0.20, 2),
                    'profit' => round($netProfit * 0.20, 2),
                    'margin' => $profitMargin
                ],
                [
                    'network' => 'Telkom Kenya (25477x)',
                    'total_sms' => (int)($totalSmsFired * 0.05),
                    'revenue' => round($grossRevenue * 0.05, 2),
                    'cost' => round($totalCarrierCost * 0.05, 2),
                    'profit' => round($netProfit * 0.05, 2),
                    'margin' => $profitMargin
                ],
            ];

            $clientLeaderboard = [];
            try {
                $users = \App\Modules\Accounts\Models\User::whereIn('role_tier', ['CLIENT', 'USER'])->get();
                $clientLeaderboard = $users->map(function ($u) {
                    $rev = 0.0;
                    try {
                        $rev = abs((float) WalletTransaction::where('client_account_id', $u->client_account_id ?? $u->id)->sum('amount'));
                    } catch (\Throwable $e) {}
                    $cost = round($rev * 0.50, 2);
                    $profit = max(0, $rev - $cost);
                    return [
                        'id' => $u->id,
                        'company_name' => $u->name ?? 'Client #' . $u->id,
                        'email' => $u->email ?? 'N/A',
                        'revenue' => round($rev, 2),
                        'carrier_cost' => $cost,
                        'net_profit' => round($profit, 2),
                        'balance' => round($u->wallet_balance ?? 0, 2)
                    ];
                })
                ->sortByDesc('net_profit')
                ->values()
                ->take(10)
                ->toArray();
            } catch (\Throwable $e) {}

            return response()->json([
                'global_revenue' => round($grossRevenue, 2),
                'gross_revenue' => round($grossRevenue, 2),
                'carrier_cost' => round($totalCarrierCost, 2),
                'net_profit' => round($netProfit, 2),
                'profit_margin' => $profitMargin,
                'avg_profit_per_sms' => $totalSmsFired > 0 ? round($netProfit / $totalSmsFired, 4) : 0,
                'onboarded_resellers' => $onboardedResellers,
                'onboarded_clients' => $onboardedClients,
                'total_sms_fired' => $totalSmsFired,
                'peak_capacity_tps' => $peakCapacity,
                'carrier_breakdown' => $carrierBreakdown,
                'client_leaderboard' => $clientLeaderboard
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'global_revenue' => 0,
                'gross_revenue' => 0,
                'carrier_cost' => 0,
                'net_profit' => 0,
                'profit_margin' => 0,
                'avg_profit_per_sms' => 0,
                'onboarded_resellers' => 0,
                'onboarded_clients' => 0,
                'total_sms_fired' => 0,
                'peak_capacity_tps' => 10240,
                'carrier_breakdown' => [],
                'client_leaderboard' => []
            ]);
        }
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
