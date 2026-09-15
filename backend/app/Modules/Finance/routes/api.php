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
        Route::get('/export/transactions', [\App\Modules\Finance\Controllers\ExportController::class, 'exportTransactions']);
        Route::get('/export/invoices', [\App\Modules\Finance\Controllers\ExportController::class, 'exportInvoices']);
    });

// Public M-Pesa Callback Routes (Accessible by Safaricom Daraja Servers)
Route::post('/webhooks/mpesa/callback', [FinanceController::class, 'stkCallback']);
Route::post('/finance/mpesa/stkcallback', [FinanceController::class, 'stkCallback']);
Route::post('/mpesa/stkcallback', [FinanceController::class, 'stkCallback']);
