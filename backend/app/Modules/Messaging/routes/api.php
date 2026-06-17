<?php

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

    // Sender IDs (Section 5.5)
    Route::get('/sender-ids', [SenderIDController::class, 'index']);
    Route::post('/sender-ids', [SenderIDController::class, 'store']);
    Route::post('/sender-ids/{id}/fallback', [SenderIDController::class, 'setFallback']);
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
Route::middleware(['auth:sanctum', 'tenant.active', 'admin.password.expiry', 'role.admin'])->group(function () {
    Route::get('/admin/routes', [\App\Modules\Messaging\Controllers\RouteController::class, 'index']);
    Route::put('/admin/routes/{id}', [\App\Modules\Messaging\Controllers\RouteController::class, 'update']);
});


