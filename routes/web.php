<?php

use Illuminate\Support\Facades\Route;
use App\Services\InteractiveMenuService;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-interactive-menu', function (Request $request, InteractiveMenuService $service) {
    $phoneNumber = $request->query('phone_number');
    if (empty($phoneNumber)) {
        return response()->json(['error' => 'phone_number is required'], 400);
    }

    $payload = $service->buildInteractiveListPayload();
    if ($request->boolean('debug')) {
        \Log::info('interactive_payload_debug', [
            'phone_number' => $phoneNumber,
            'payload' => $payload,
        ]);
    }

    $service->sendMainMenu((string) $phoneNumber);

    return response()->json(['status' => 'sent'], 200);
});

Route::post('/test-interactive-menu', function (Request $request) {
    info('test-interactive-menu   --- IGNORE ---');
    info('Received interactive response: ' . $request->getContent());
    return response()->json(['status' => 'sent'], 200);
});

Route::post('/test-interactive-menu', function (Request $request) {

    info('test-interactive-menu   --- IGNORE ---');
    info('Received interactive response: ' . $request->getContent());

    return response()->json(['status' => 'sent'], 200);
});

// Removed Chatwoot form test: interactive lists are now sent directly to WhatsApp.
