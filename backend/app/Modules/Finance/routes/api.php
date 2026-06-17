<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Finance\Controllers\FinanceController;

Route::middleware(['auth:sanctum', 'tenant.active', 'role.client'])
    ->prefix('client/finance')
    ->group(function () {
        Route::get('/transactions', [FinanceController::class, 'transactions']);
        Route::get('/invoices', [FinanceController::class, 'invoices']);
        Route::get('/mpesa', [FinanceController::class, 'mpesa']);
        Route::post('/mpesa/stkpush', [FinanceController::class, 'stkPush']);
    });

// Public M-Pesa Callback Route
Route::post('/webhooks/mpesa/callback', [FinanceController::class, 'stkCallback']);
