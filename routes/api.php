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

// Ask AI — channel-agnostic canonical API endpoint (external channels: SMS, Messenger, WhatsApp, mobile, CRM).
// Requires Sanctum token authentication. Returns HTTP 401 for unauthenticated requests.
Route::middleware(['auth:sanctum', 'throttle:ask-ai-api'])
    ->post('/ask-ai/ask', [\App\Http\Controllers\AskAi\AskAiApiController::class, 'ask'])
    ->name('api.ask-ai.ask');
