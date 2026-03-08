<?php

use App\Http\Controllers\Internal\MetricsController;
use App\Http\Controllers\Webhook\ChatwootWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/chatwoot', [ChatwootWebhookController::class, 'handle']);
Route::get('/internal/metrics', [MetricsController::class, 'index']);
