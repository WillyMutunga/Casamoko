<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Accounts\Controllers\AuthController;

use App\Modules\Accounts\Controllers\AdminController;
use App\Modules\Accounts\Controllers\ResellerController;

use App\Modules\Accounts\Controllers\KYCController;
use App\Modules\Accounts\Controllers\TeamController;
use App\Modules\Accounts\Controllers\AnalyticsController;

// Public auth endpoints
Route::prefix('accounts')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('brute.limit');
    Route::post('/register', [AuthController::class, 'register']);

// Protected session endpoints
Route::middleware(['auth:sanctum', 'tenant.active', 'admin.password.expiry'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    // --- System Supervisor (Admin) endpoints ---
    Route::middleware(['role.admin'])->prefix('admin')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'adminDashboard']);
        Route::get('/resellers', [AdminController::class, 'listResellers']);
        Route::get('/clients', [AdminController::class, 'listClients']);
        Route::post('/resellers', [AdminController::class, 'createReseller']);
        Route::put('/resellers/{id}', [AdminController::class, 'updateReseller']);
        Route::delete('/resellers/{id}', [AdminController::class, 'deleteReseller']);
        Route::post('/resellers/{id}/reset-password', [AdminController::class, 'resetResellerPassword']);
        Route::get('/sender-ids/pending', [AdminController::class, 'listPendingSenders']);
        Route::post('/sender-ids/{id}/approve', [AdminController::class, 'approveSender']);
        Route::post('/sender-ids/{id}/reject', [AdminController::class, 'rejectSender']);
        Route::post('/sender-ids/{id}/mno-approval', [AdminController::class, 'updateMnoApproval']);
        Route::get('/audit-logs', [AdminController::class, 'listAuditLogs']);
        Route::post('/client/{id}/status', [KYCController::class, 'updateStatus']);
        Route::post('/client/{id}/wallet-adjust', [AdminController::class, 'adjustWallet']);
    });

    // --- Channel Partner (Reseller) endpoints ---
    Route::middleware(['role.reseller'])->prefix('reseller')->group(function () {
        Route::get('/clients', [ResellerController::class, 'listClients']);
        Route::post('/clients', [ResellerController::class, 'onboardClient']);
    });

    // --- Client Corporate endpoints ---
    Route::middleware(['role.client'])->prefix('client')->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'getClientMetrics']);

        // API Keys
        Route::get('/api-keys', [\App\Modules\Accounts\Controllers\ApiKeyController::class, 'index']);
        Route::post('/api-keys', [\App\Modules\Accounts\Controllers\ApiKeyController::class, 'store']);
        Route::delete('/api-keys/{id}', [\App\Modules\Accounts\Controllers\ApiKeyController::class, 'revoke']);
        
        Route::post('/kyc-upload', [KYCController::class, 'uploadKYC']);
        Route::get('/team', [TeamController::class, 'listTeam']);
        Route::post('/team', [TeamController::class, 'addTeamMember']);
        Route::get('/activities', [TeamController::class, 'listActivities']);
    });
});
});
