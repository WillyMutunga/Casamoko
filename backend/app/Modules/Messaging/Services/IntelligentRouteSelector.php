<?php

namespace App\Modules\Messaging\Services;

use App\Modules\Messaging\Models\Route;
use Illuminate\Support\Facades\Log;

class IntelligentRouteSelector
{
    /**
     * Detect mobile network operator from Kenyan MSISDN prefix.
     */
    public function detectNetwork(string $msisdn): string
    {
        $clean = preg_replace('/[^0-9]/', '', $msisdn);
        
        if (str_starts_with($clean, '254')) {
            $prefix = substr($clean, 3, 2);
        } else {
            $prefix = substr($clean, 0, 2);
        }

        if (in_array($prefix, ['70', '71', '72', '74', '79', '11', '12'])) {
            return 'SAFARICOM';
        } elseif (in_array($prefix, ['73', '75', '78', '10'])) {
            return 'AIRTEL';
        } elseif ($prefix === '77') {
            return 'TELKOM';
        }

        return 'SAFARICOM'; // Default fallback
    }

    /**
     * Select the optimal active route for a given MSISDN based on QoS, Cost, and Priority.
     */
    public function selectBestRoute(string $msisdn): ?Route
    {
        $network = $this->detectNetwork($msisdn);
        $routes = Route::where('destination_network', $network)
            ->where('is_active', true)
            ->get();

        if ($routes->isEmpty()) {
            Log::warning("No active routes found for network {$network}");
            return null;
        }

        // Sort routes using intelligent QoS scoring formula:
        // QoS Score = (Delivery Rate * 0.70) + ((1000 - Latency) / 10 * 0.30)
        // If QoS Score within 5% (5 points) tolerance, select by lowest cost, then by administrative priority.
        $sorted = $routes->sort(function ($a, $b) {
            $qA = ((float)$a->delivery_rate_score * 0.7) + ((1000 - min((float)$a->latency_score, 1000)) / 10.0 * 0.3);
            $qB = ((float)$b->delivery_rate_score * 0.7) + ((1000 - min((float)$b->latency_score, 1000)) / 10.0 * 0.3);

            if (abs($qA - $qB) <= 5.00) {
                if (abs((float)$a->cost_per_sms - (float)$b->cost_per_sms) > 0.0001) {
                    return (float)$a->cost_per_sms <=> (float)$b->cost_per_sms;
                }
                return $b->priority <=> $a->priority;
            }

            return $qB <=> $qA;
        });

        $chosen = $sorted->first();
        Log::info("IntelligentRouteSelector: Selected route '{$chosen->name}' for prefix '{$network}'");
        return $chosen;
    }

    /**
     * Degrade route scores dynamically upon connection or dispatch failure to trigger failover.
     */
    public function degradeRoute(Route $route): void
    {
        $oldDelivery = (float)$route->delivery_rate_score;
        $oldLatency = (int)$route->latency_score;

        $newDelivery = max(0.00, $oldDelivery - 15.00);
        $newLatency = min(1000, $oldLatency + 200);

        $route->update([
            'delivery_rate_score' => $newDelivery,
            'latency_score' => $newLatency
        ]);

        Log::warning("IntelligentRouteSelector: Degraded route '{$route->name}' from QoS DR:{$oldDelivery}%/L:{$oldLatency}ms to DR:{$newDelivery}%/L:{$newLatency}ms");
    }
}
