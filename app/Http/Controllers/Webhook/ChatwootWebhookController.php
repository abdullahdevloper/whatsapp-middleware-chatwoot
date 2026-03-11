<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\EventNormalizer\EventNormalizer;
use App\Services\SessionResolver\ActiveSessionConflict;
use App\Services\SessionResolver\SessionLifecycleService;
use App\Services\FlowEngine\FlowEngine;
use App\Models\ChatwootInbox;
use App\Services\Observability\Metrics;
use App\Services\InteractiveRouter;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

class ChatwootWebhookController extends Controller
{
    public function __construct(
        private readonly EventNormalizer $normalizer,
        private readonly SessionLifecycleService $lifecycleService,
        private readonly FlowEngine $flowEngine,
        private readonly InteractiveRouter $interactiveRouter,
    ) {}

    public function handle(Request $request)
    {
        $routingId = $request->input('content_attributes.interactive_response.id');

        if ($routingId) {
            // مبروك! لديك الآن الـ ID ويمكنك توجيه الرسالة برمجياً
            Log::info("Received Routing ID: " . $routingId);
        }
        Metrics::increment('webhooks_total');
        Log::info('Received Chatwoot webhook: ' . $request->getContent());

        $raw = $request->getContent();

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::info('Invalid JSON payload: ' . $e->getMessage());

            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        try {
            $normalized = $this->normalizer->normalize($payload);
        } catch (InvalidArgumentException $e) {
            Log::info('Normalization error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }

        $phoneNumber = $normalized['sender_phone'] ?? null;
        $messageType = $normalized['message_type'] ?? null;
        $content = $normalized['message_content'] ?? null;
        $senderType = $normalized['sender_type'] ?? null;
        $inboxId = $normalized['inbox_id'] ?? null;

        if ($senderType !== null && $senderType !== 'Contact') {
            return response()->json(['status' => 'ignored_non_contact'], 200);
        }

        if ($messageType !== 'incoming') {
            return response()->json(['status' => 'ignored_non_incoming'], 200);
        }

        $testModeEnabled = (bool) config('middleware.test_mode_enabled');
        $testPhone = (string) config('middleware.test_phone_number');

        if ($testModeEnabled) {
            if (empty($phoneNumber) || $phoneNumber !== $testPhone) {
                Log::info('test_mode_blocked_message', [
                    'phone' => $phoneNumber,
                ]);
                return response()->json(['status' => 'test_mode_blocked'], 200);
            }
            Log::info('test_mode_allowed_message', [
                'phone' => $phoneNumber,
            ]);
        }

        $phoneNumberId = null;
        if ($inboxId !== null) {
            $inbox = ChatwootInbox::where('chatwoot_inbox_id', $inboxId)->first();
            $phoneNumberId = $inbox?->whatsapp_phone_number_id;
            if ($phoneNumberId !== null) {
                Log::info('whatsapp_channel_resolved', [
                    'inbox_id' => $inboxId,
                    'phone_number_id' => $phoneNumberId,
                    'phone' => $phoneNumber,
                ]);
            }
        }

        if (empty($phoneNumberId)) {
            Log::info('whatsapp_channel_missing', [
                'inbox_id' => $inboxId,
                'phone' => $phoneNumber,
            ]);
            return response()->json(['status' => 'missing_whatsapp_channel'], 200);
        }

        $replyId = $this->extractInteractiveReplyId($payload);
        if ($replyId !== null && !empty($phoneNumber)) {
            Log::info('interactive_routing_detected', [
                'routing_id' => $replyId,
                'phone' => $phoneNumber,
            ]);
            $this->interactiveRouter->handleInteractiveReply($phoneNumberId, $phoneNumber, $inboxId, $replyId);
            return response()->json(['status' => 'interactive_handled'], 200);
        }

        if ($messageType === 'incoming' && !empty($phoneNumber)) {
            $this->interactiveRouter->handleTextMessage($phoneNumberId, $phoneNumber, $inboxId, (string) $content);
            return response()->json(['status' => 'text_handled'], 200);
        }

        return response()->json(['status' => 'ignored'], 200);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505';
    }

    private function logEvent(string $event, int $tenantId, array $normalized, string $eventUid): void
    {
        Log::info($event, [
            'tenant_id' => $tenantId,
            'conversation_id' => $normalized['conversation_id'] ?? null,
            'inbox_id' => $normalized['inbox_id'] ?? null,
            'message_id' => $normalized['message_id'] ?? null,
            'event_uid' => $eventUid,
        ]);
    }

    private function logTestModeEvent(string $event, int $tenantId, array $normalized): void
    {
        Log::info($event, [
            'tenant_id' => $tenantId,
            'conversation_id' => $normalized['conversation_id'] ?? null,
            'inbox_id' => $normalized['inbox_id'] ?? null,
            'message_id' => $normalized['message_id'] ?? null,
            'sender_phone' => $normalized['sender_phone'] ?? null,
        ]);
    }

    private function buildEventUid(array $normalized, string $raw): string
    {
        $eventType = $normalized['event_type'] ?? 'unknown';
        $messageType = $normalized['message_type'] ?? 'unknown';
        $senderType = $normalized['sender_type'] ?? 'unknown';
        $conversationId = $normalized['conversation_id'] ?? 'unknown';

        if (!empty($normalized['message_external_id'])) {
            $base = implode('|', [
                'ext',
                $normalized['message_external_id'],
                $eventType,
                $messageType,
                $senderType,
                $conversationId,
            ]);

            return hash('sha256', $base);
        }

        if (!empty($normalized['message_uid'])) {
            $base = implode('|', [
                'msg',
                $normalized['message_uid'],
                $eventType,
                $messageType,
                $senderType,
                $conversationId,
            ]);

            return hash('sha256', $base);
        }

        return hash('sha256', $raw);
    }

    private function isIncomingUserMessage(array $normalized): bool
    {
        $messageType = $normalized['message_type'] ?? null;
        $senderType = $normalized['sender_type'] ?? null;

        if ($messageType !== null && $messageType !== 'incoming') {
            return false;
        }

        return $senderType !== 'agent';
    }

    private function extractInteractiveReplyId(array $payload): ?string
    {
        $id = data_get($payload, 'content_attributes.interactive_response.list_reply.id')
            ?? data_get($payload, 'message.content_attributes.interactive_response.list_reply.id')
            ?? data_get($payload, 'content_attributes.interactive_response.button_reply.id')
            ?? data_get($payload, 'message.content_attributes.interactive_response.button_reply.id')
            ?? data_get($payload, 'content_attributes.interactive_response.reply.id')
            ?? data_get($payload, 'message.content_attributes.interactive_response.reply.id');

        return $id !== null ? (string) $id : null;
    }
}
