<?php

use App\Http\Controllers\Internal\MetricsController;
use App\Http\Controllers\Webhook\ChatwootWebhookController;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Request;

Route::post('/webhooks/chatwoot', [ChatwootWebhookController::class, 'handle']);
Route::get('/internal/metrics', [MetricsController::class, 'index']);
Route::post('/test-interactive-menu', function (Request $request) {
    info($request);
    info('test-interactive-menu   --- IGNORE ---');
    info('Received interactive response: ' . $request->getContent());
    return response()->json(['status' => 'sent'], 200);
});