<?php

namespace App\Services\ChatwootClient;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use App\Services\Observability\Metrics;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ChatwootClient
{
    public function sendMessage(
        int $accountId,
        int $conversationId,
        string $content,
        int $tenantId,
        ?int $inboxId,
        ?string $messageId
    ): void
    {
        $baseUrl = rtrim(config('chatwoot.base_url'), '/');
        $token = config('chatwoot.api_token');
        $header = config('chatwoot.access_token_header');

        if (empty($token)) {
            throw new RuntimeException('CHATWOOT_API_TOKEN is not configured.');
        }

        $url = $baseUrl."/api/v1/accounts/{$accountId}/conversations/{$conversationId}/messages";

        try {
            Http::withHeaders([
                $header => $token,
                'Content-Type' => 'application/json',
            ])
                ->retry(
                    (int) config('chatwoot.retry_times', 3),
                    (int) config('chatwoot.retry_delay_ms', 200),
                    function ($exception, $request, $response) {
                        if ($exception instanceof ConnectionException) {
                            return true;
                        }

                        if ($response) {
                            return $response->serverError() || $response->status() === 429;
                        }

                        return false;
                    }
                )
                ->timeout((int) config('chatwoot.timeout', 10))
                ->post($url, [
                    'content' => $content,
                    'message_type' => 'outgoing',
                    'private' => false,
                ])
                ->throw();

            Metrics::increment('messages_sent_total');
            Log::info('message_sent', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'inbox_id' => $inboxId,
                'message_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            Metrics::increment('errors_total');
            Log::error('chatwoot_api_failure', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'inbox_id' => $inboxId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function assignAgent(
        int $accountId,
        int $conversationId,
        int $agentId,
        int $tenantId,
        ?int $inboxId,
        ?string $messageId
    ): void {
        $this->post(
            $accountId,
            "/api/v1/accounts/{$accountId}/conversations/{$conversationId}/assignments",
            ['assignee_id' => $agentId],
            $tenantId,
            $conversationId,
            $inboxId,
            $messageId
        );
    }

    public function addLabel(
        int $accountId,
        int $conversationId,
        string $label,
        int $tenantId,
        ?int $inboxId,
        ?string $messageId
    ): void {
        $this->post(
            $accountId,
            "/api/v1/accounts/{$accountId}/conversations/{$conversationId}/labels",
            ['labels' => [$label]],
            $tenantId,
            $conversationId,
            $inboxId,
            $messageId
        );
    }

    public function resolveConversation(
        int $accountId,
        int $conversationId,
        int $tenantId,
        ?int $inboxId,
        ?string $messageId
    ): void {
        $this->post(
            $accountId,
            "/api/v1/accounts/{$accountId}/conversations/{$conversationId}/toggle_status",
            ['status' => 'resolved'],
            $tenantId,
            $conversationId,
            $inboxId,
            $messageId
        );
    }

    public function sendPrivateNote(
        int $accountId,
        int $conversationId,
        string $text,
        int $tenantId,
        ?int $inboxId,
        ?string $messageId
    ): void {
        $this->post(
            $accountId,
            "/api/v1/accounts/{$accountId}/conversations/{$conversationId}/messages",
            [
                'content' => $text,
                'message_type' => 'outgoing',
                'private' => true,
            ],
            $tenantId,
            $conversationId,
            $inboxId,
            $messageId
        );
    }

    private function post(
        int $accountId,
        string $path,
        array $payload,
        int $tenantId,
        int $conversationId,
        ?int $inboxId,
        ?string $messageId
    ): void {
        $baseUrl = rtrim(config('chatwoot.base_url'), '/');
        $token = config('chatwoot.api_token');
        $header = config('chatwoot.access_token_header');

        if (empty($token)) {
            throw new RuntimeException('CHATWOOT_API_TOKEN is not configured.');
        }

        $url = $baseUrl.$path;

        try {
            Http::withHeaders([
                $header => $token,
                'Content-Type' => 'application/json',
            ])
                ->retry(
                    (int) config('chatwoot.retry_times', 3),
                    (int) config('chatwoot.retry_delay_ms', 200),
                    function ($exception, $request, $response) {
                        if ($exception instanceof ConnectionException) {
                            return true;
                        }

                        if ($response) {
                            return $response->serverError() || $response->status() === 429;
                        }

                        return false;
                    }
                )
                ->timeout((int) config('chatwoot.timeout', 10))
                ->post($url, $payload)
                ->throw();

            Metrics::increment('messages_sent_total');
            Log::info('message_sent', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'inbox_id' => $inboxId,
                'message_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            Metrics::increment('errors_total');
            Log::error('chatwoot_api_failure', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'inbox_id' => $inboxId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
