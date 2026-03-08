<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppService
{
    public function sendText(string $phoneNumberId, string $phoneNumber, string $text): void
    {
        $token = env('WHATSAPP_ACCESS_TOKEN');

        if (empty($token) || empty($phoneNumberId)) {
            throw new RuntimeException('WHATSAPP_ACCESS_TOKEN or phone_number_id is not configured.');
        }

        $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => 'text',
            'text' => [
                'body' => $text,
            ],
        ];

        Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
        ])->post($url, $payload)->throw();

        Log::info('whatsapp_text_sent', [
            'phone_number' => $phoneNumber,
            'phone_number_id' => $phoneNumberId,
        ]);
    }
    public function sendInteractiveList(
        string $phoneNumberId,
        string $phoneNumber,
        string $header,
        string $body,
        string $buttonText,
        array $sections
    ): void {
        $token = env('WHATSAPP_ACCESS_TOKEN');

        if (empty($token) || empty($phoneNumberId)) {
            throw new RuntimeException('WHATSAPP_ACCESS_TOKEN or phone_number_id is not configured.');
        }

        $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => $header,
                ],
                'body' => [
                    'text' => $body,
                ],
                'action' => [
                    'button' => $buttonText,
                    'sections' => $sections,
                ],
            ],
        ];

        Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
        ])->post($url, $payload)->throw();

        Log::info('whatsapp_interactive_sent', [
            'phone_number' => $phoneNumber,
            'phone_number_id' => $phoneNumberId,
        ]);
    }
}
