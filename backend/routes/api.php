<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/delete-admin-wildcard', function () {
    \ = \App\Modules\Accounts\Models\Shortcode::where('client_account_id', 7)->pluck('id');
    \ = \App\Modules\Messaging\Models\Keyword::whereIn('shortcode_id', \)->where('keyword', '*')->delete();
    return response()->json(['status' => 'success', 'deleted' => \]);
});
