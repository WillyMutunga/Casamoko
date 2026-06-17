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

        $validated = $request->validate([
            'priority' => 'nullable|integer|min:1',
            'window_size' => 'nullable|integer|min:1',
            'tps_limit' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'use_tls' => 'nullable|boolean',
            'bind_type' => 'nullable|string|in:TRANSMITTER,RECEIVER,TRANSCEIVER,REST',
            'cost_per_sms' => 'nullable|numeric|min:0',
        ]);

        $route->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Route updated successfully',
            'data' => $route
        ]);
    }


}
