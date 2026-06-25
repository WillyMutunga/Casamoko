<?php

namespace App\Modules\Messaging\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Messaging\Models\Route;
use Illuminate\Support\Facades\Log;

class RouteController extends Controller
{
    /**
     * List all carrier routes.
     */
    public function index()
    {
        $routes = Route::with('carrier')->get();
        return response()->json([
            'success' => true,
            'data' => $routes
        ]);
    }
    
    public function getGatewayBalance()
    {
        try {
            $gateway = app(\App\Modules\Messaging\Services\Gateways\SafaricomSmsGateway::class);
            $balance = $gateway->getBalance();
            return response()->json([
                'success' => true,
                'data' => [
                    'safaricom_balance' => $balance
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update route parameters.
     */
    public function update(Request $request, $id)
    {
        $route = Route::find($id);

        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found'
            ], 404);
        }

        \Illuminate\Support\Facades\Log::info("Route Update Request for ID: $id", $request->all());

        $validated = $request->validate([
            'priority' => 'nullable|integer|min:1',
            'window_size' => 'nullable|integer|min:1',
            'tps_limit' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'use_tls' => 'nullable|boolean',
            'bind_type' => 'nullable|string|in:TRANSMITTER,RECEIVER,TRANSCEIVER,REST',
            'cost_per_sms' => 'nullable|numeric|min:0',
        ]);

        \Illuminate\Support\Facades\Log::info("Validated Data:", $validated);

        // Force explicit assignment instead of mass assignment to bypass any guarded issues
        if (isset($validated['cost_per_sms'])) {
            $route->cost_per_sms = $validated['cost_per_sms'];
        }
        if (isset($validated['priority'])) {
            $route->priority = $validated['priority'];
        }
        if (isset($validated['is_active'])) {
            $route->is_active = $validated['is_active'];
        }
        
        $route->save();

        \Illuminate\Support\Facades\Log::info("Route Saved:", $route->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Route updated successfully',
            'data' => $route
        ]);
    }


}
