<?php

Route::get('/fix-sender-id', function() {
    \Illuminate\Support\Facades\DB::table('sender_ids')->update(['sender_id' => \Illuminate\Support\Facades\DB::raw('UPPER(sender_id)')]);
    return "All Sender IDs have been successfully updated to UPPERCASE! You can now go to Quick Send and use CASAMOKO.";
});

Route::get('/clear-cache', function() {
    $out = [];
    try { \Illuminate\Support\Facades\Artisan::call('optimize:clear'); $out[] = 'Laravel Cache Cleared'; } catch(\Exception $e) {}
    try { \Illuminate\Support\Facades\Artisan::call('queue:restart'); $out[] = 'Queue Restarted'; } catch(\Exception $e) {}
    if (function_exists('opcache_reset')) { opcache_reset(); $out[] = 'OPcache Reset'; }
    return implode(' | ', $out);
});

Route::get('/force-base-cost', function(\Illuminate\Http\Request $request) {
    $cost = $request->query('cost', 1.2);
    
    // Seed a route if the table is completely empty
    if (\Illuminate\Support\Facades\DB::table('routes')->count() === 0) {
        // Ensure a carrier exists first to prevent foreign key errors
        $carrierId = \Illuminate\Support\Facades\DB::table('carriers')->value('id');
        if (!$carrierId) {
            $carrierId = \Illuminate\Support\Facades\DB::table('carriers')->insertGetId([
                'name' => 'Safaricom Direct',
                'binding_details' => json_encode(['host' => '192.168.1.1', 'port' => 2775]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        \Illuminate\Support\Facades\DB::table('routes')->insert([
            'name' => 'Safaricom Direct',
            'carrier_id' => $carrierId,
            'cost_per_sms' => $cost,
            'tps_limit' => 100,
            'priority' => 1,
            'is_active' => true,
            'destination_network' => 'Safaricom',
            'bind_type' => 'TRANSCEIVER',
            'window_size' => 10,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        return "Seeded Safaricom Direct route and set base cost to: " . $cost;
    }

    \Illuminate\Support\Facades\DB::table('routes')->update(['cost_per_sms' => $cost]);
    return "Force updated all existing routes in the database to a cost of: " . $cost;
});

Route::get('/debug-log', function() {
    $logFile = storage_path('logs/laravel.log');
    if (!file_exists($logFile)) return "No log file";
    $lines = file($logFile);
    return implode("", array_slice($lines, -100));
});

Route::get('/debug-cost', function() {
    $primaryRoute = \App\Modules\Messaging\Models\Route::where('is_active', true)->orderBy('priority', 'asc')->first();
    $currentBaseCost = $primaryRoute ? (float) $primaryRoute->cost_per_sms : 0.5000;
    return response()->json([
        'primary_route' => $primaryRoute,
        'calculated_cost' => $currentBaseCost
    ]);
});

Route::get('/check-logs', function() {
    return \App\Modules\Messaging\Models\MessageRecord::orderBy('id', 'desc')->take(5)->get();
});

Route::get('/debug-queue', function() {
    $record = \App\Modules\Messaging\Models\MessageRecord::where('status', 'QUEUED')->orderBy('id', 'asc')->first();
    if (!$record) return "No queued messages found.";
    
    try {
        // Run the gateway directly
        $gateway = new \App\Modules\Messaging\Services\Gateways\SafaricomSmsGateway();
        $rawResult = $gateway->send('CASAMOKO', $record->contact->msisdn ?? '254742765445', 'Test message bypass');

        // Force update the record directly so the user can finally see it on the dashboard
        $record->update([
            'status' => $rawResult['status'],
            'network_status_code' => $rawResult['error_code'] ?? 'SC0011'
        ]);

        return "Successfully Forced Update. New DB Status: " . $record->status . " | Raw Gateway Result: " . json_encode($rawResult);
    } catch (\Exception $e) {
        return "Gateway Failed with Error: " . $e->getMessage() . "\n" . $e->getTraceAsString();
    }
});

Route::get('/test-safaricom', function() {
    try {
        $gateway = new \App\Modules\Messaging\Services\Gateways\SafaricomSmsGateway();
        
        $reflection = new \ReflectionClass($gateway);
        
        $method = $reflection->getMethod('getJwtToken');
        $method->setAccessible(true);
        $token = $method->invoke($gateway);

        $cpIdProp = $reflection->getProperty('cpId');
        $cpIdProp->setAccessible(true);
        $cpId = $cpIdProp->getValue($gateway);

        // Attempt a test send using standard bulkSMS
        $sendResponse = null;
        try {
            $sendResponse = $gateway->send('CASAMOKO', '254742765445', 'Test message');
        } catch (\Exception $sendEx) {
            $sendResponse = $sendEx->getMessage();
        }

        // Attempt a raw connection to the other URL to see if it times out
        $altResponse = null;
        try {
            $altReq = \Illuminate\Support\Facades\Http::timeout(10)->withHeaders([
                'accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                'X-Country' => 'KEN',
                'Content-Type' => 'application/json',
                'X-Authorization' => 'Bearer ' . $token
            ])->post('https://dsdp-apinb.safaricom.com/api/public/SDP/sendSMSRequest', []);
            $altResponse = $altReq->status() . ': ' . $altReq->body();
        } catch (\Exception $altEx) {
            $altResponse = $altEx->getMessage();
        }

        return response()->json([
            'success' => true,
            'token_received' => (bool)$token,
            'cpId_used' => $cpId,
            'cms_bulksms_test' => $sendResponse,
            'sdp_send_test' => $altResponse
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

Route::get('/debug-messages', function() {
    return response()->json(\App\Models\MessageRecord::orderBy('id', 'desc')->take(20)->get());
});

Route::get('/debug-jobs', function() {
    return response()->json(\Illuminate\Support\Facades\DB::table('jobs')->get());
});

Route::get('/process-queue', function () {
    try {
        $basePath = base_path();
        $cronCommand = "/usr/local/bin/php {$basePath}/artisan queue:work --stop-when-empty";
        
        // Attempt to run it directly
        \Illuminate\Support\Facades\Artisan::call('queue:work', ['--stop-when-empty' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Queue worker executed!',
            'your_exact_cron_command' => $cronCommand,
            'server_path' => $basePath,
            'worker_output' => \Illuminate\Support\Facades\Artisan::output()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
});

Route::get('/debug-routes', function () {
    return \App\Modules\Messaging\Models\Route::all();
});

Route::get('/debug-logs', function () {
    $logFile = storage_path('logs/laravel.log');
    if (!file_exists($logFile)) {
        return response()->json(['error' => 'Log file not found']);
    }
    
    try {
        $fp = fopen($logFile, 'r');
        if (!$fp) return response()->json(['error' => 'Failed to open log file']);
        
        fseek($fp, 0, SEEK_END);
        $pos = ftell($fp);
        
        // Read last 20KB to ensure we catch recent errors
        $bytesToRead = min(20000, $pos);
        fseek($fp, -$bytesToRead, SEEK_END);
        $text = fread($fp, $bytesToRead);
        fclose($fp);
        
        return response()->json(['logs' => mb_convert_encoding($text, 'UTF-8', 'UTF-8')]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});
Route::get('/test-safaricom-gateway', function () {
    try {
        $gateway = app(\App\Modules\Messaging\Services\Gateways\SafaricomSmsGateway::class);
        $result = $gateway->send('CASAMOKO', '254700000000', 'Test Message from Automated Diagnostics');
        return response()->json([
            'success' => true,
            'status_reached' => true,
            'gateway_result' => $result
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'status_reached' => false,
            'error_message' => $e->getMessage(),
            'error_trace' => $e->getTraceAsString()
        ]);
    }
});

Route::get('/migrate-now', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return response()->json(['output' => \Illuminate\Support\Facades\Artisan::output()]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

Route::get('/debug-keywords', function () {
    return response()->json(\App\Modules\Messaging\Models\Shortcode::all());
});

Route::get('/fix-keyword', function () {
    $client = \App\Modules\Accounts\Models\ClientAccount::first();
    \App\Modules\Messaging\Models\Keyword::whereNull('client_account_id')->update(['client_account_id' => $client->id]);
    return response()->json(['status' => 'fixed', 'client_id' => $client->id]);
});

use Illuminate\Support\Facades\Route;
use App\Modules\Messaging\Controllers\QuickSendController;
use App\Modules\Messaging\Controllers\CampaignController;
use App\Modules\Messaging\Controllers\ShortcodeController;
use App\Modules\Messaging\Controllers\SenderIDController;

// Secure client and campaign messaging endpoints group
Route::middleware(['auth:sanctum', 'tenant.active', 'admin.password.expiry', 'role.client'])->group(function () {
    
    // Quick send (specific campaign managers or client admins)
    Route::post('/quick-send', [QuickSendController::class, 'quickSend'])
        ->middleware('role.sub:CLIENT_ADMIN,CAMPAIGN_MANAGER');

    // Campaigns (Section 5.3)
    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::post('/campaigns/preview', [CampaignController::class, 'preview']);
    Route::delete('/campaigns/{id}', [CampaignController::class, 'destroy']);

    // Templates
    Route::get('/templates', [\App\Modules\Messaging\Controllers\TemplateController::class, 'index']);
    Route::post('/templates', [\App\Modules\Messaging\Controllers\TemplateController::class, 'store']);
    Route::delete('/templates/{id}', [\App\Modules\Messaging\Controllers\TemplateController::class, 'destroy']);

    Route::post('/campaigns/{id}/action', [CampaignController::class, 'action']);
    Route::post('/campaigns/{id}/approve', [CampaignController::class, 'approve']);
    Route::post('/campaigns/{id}/duplicate', [CampaignController::class, 'duplicate']);
    Route::get('/campaigns/{id}/logs', [CampaignController::class, 'logs']);

    // Shortcodes (Section 5.4)
    Route::get('/shortcodes', [ShortcodeController::class, 'listShortcodes']);
    Route::get('/shortcodes/keywords', [ShortcodeController::class, 'listKeywords']);
    Route::post('/shortcodes/keywords', [ShortcodeController::class, 'storeKeyword']);
    Route::get('/shortcodes/mo-logs', [ShortcodeController::class, 'listMoLogs']);
    Route::get('/shortcodes/threads', [ShortcodeController::class, 'getThreadedConversations']);
    Route::post('/shortcodes/reply', [ShortcodeController::class, 'replyToThread']);

    // Sender IDs (Section 5.5)
    Route::get('/sender-ids', [SenderIDController::class, 'index']);
    Route::post('/sender-ids', [SenderIDController::class, 'store']);
    Route::post('/sender-ids/{id}/fallback', [SenderIDController::class, 'setFallback']);

    // Global Reports
    Route::get('/client/reports/logs', [\App\Modules\Messaging\Controllers\ReportsController::class, 'getLogs']);
    Route::get('/client/reports/export', [\App\Modules\Messaging\Controllers\ReportsController::class, 'exportCsv']);

    // Analytics Dashboard
    Route::get('/analytics', [\App\Modules\Messaging\Controllers\AnalyticsController::class, 'index']);
});

// Public DLR webhook called by carrier networks
Route::post('/dlr-webhook', [\App\Modules\Messaging\Controllers\DlrWebhookController::class, 'handle']);
Route::post('/inbound-webhook', [\App\Modules\Messaging\Controllers\InboundWebhookController::class, 'handle']);

// Public MO (Mobile Originated) webhook called by Safaricom SDP
Route::post('/mo-webhook', [\App\Modules\Messaging\Controllers\ShortcodeController::class, 'handleSafaricomMO']);

// Developer API Endpoints
Route::middleware(['auth.api_key'])->prefix('v1')->group(function () {
    Route::post('/sms/send', [\App\Modules\Messaging\Controllers\ApiController::class, 'sendSms']);
});

// System Supervisor (Admin) Messaging Route Management
Route::middleware(['auth:sanctum', 'tenant.active', 'admin.password.expiry', 'role.admin'])
    ->prefix('messaging')
    ->group(function () {
        Route::get('/admin/routes', [\App\Modules\Messaging\Controllers\RouteController::class, 'index']);
        Route::get('/admin/routes/balance', [\App\Modules\Messaging\Controllers\RouteController::class, 'getGatewayBalance']);
        Route::put('/admin/routes/{id}', [\App\Modules\Messaging\Controllers\RouteController::class, 'update']);
        Route::post('/admin/routes/{id}/test', [\App\Modules\Messaging\Controllers\RouteController::class, 'test']);
});


