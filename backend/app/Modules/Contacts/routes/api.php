<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Contacts\Controllers\ContactController;

Route::middleware(['auth:sanctum', 'tenant.active', 'role.client'])
    ->prefix('client')
    ->group(function () {
        Route::get('/contacts/lists', [ContactController::class, 'getLists']);
        Route::post('/contacts/lists', [ContactController::class, 'createList']);
        Route::post('/contacts/lists/{list_id}/import', [ContactController::class, 'importContacts']);
        Route::get('/contacts/lists/{list_id}/export', [ContactController::class, 'exportContacts']);
        Route::get('/contacts', [ContactController::class, 'getContacts']);
    });
