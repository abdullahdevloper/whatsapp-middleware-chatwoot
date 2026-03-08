<?php

namespace App\Services\EventNormalizer;

use InvalidArgumentException;

class EventNormalizer
{
    public function normalize(array $payload): array
    {
        $accountId = $payload['account_id']
            ?? ($payload['account']['id'] ?? null);

        if ($accountId === null) {
            throw new InvalidArgumentException('Missing chatwoot_account_id in payload.');
        }

        $conversationId = $payload['conversation_id']
            ?? ($payload['conversation']['id'] ?? null);

        $messageUid = $payload['message_uid']
            ?? ($payload['message']['id'] ?? null);

        $eventType = $payload['event']
            ?? ($payload['event_type'] ?? null);

        $senderType = $payload['sender_type']
            ?? ($payload['message']['sender_type'] ?? null)
            ?? ($payload['messages'][0]['sender_type'] ?? null)
            ?? ($payload['conversation']['messages'][0]['sender_type'] ?? null);

        $messageContent = $payload['content']
            ?? ($payload['message']['content'] ?? null);

        $inboxId = $payload['inbox_id']
            ?? ($payload['inbox']['id'] ?? null);

        $messageType = $payload['message_type']
            ?? ($payload['message']['message_type'] ?? null)
            ?? ($payload['messages'][0]['message_type'] ?? null)
            ?? ($payload['conversation']['messages'][0]['message_type'] ?? null);

        if (is_int($messageType)) {
            $messageType = $messageType === 0 ? 'incoming' : 'outgoing';
        }

        $messageExternalId = $payload['source_id']
            ?? ($payload['message']['source_id'] ?? null);

        $messageId = $payload['id']
            ?? ($payload['message']['id'] ?? null)
            ?? $messageUid;

        $senderPhone = $payload['sender']['phone_number']
            ?? ($payload['message']['sender']['phone_number'] ?? null);

        return [
            'account_id' => (int) $accountId,
            'conversation_id' => $conversationId !== null ? (int) $conversationId : null,
            'message_uid' => $messageUid !== null ? (string) $messageUid : null,
            'message_id' => $messageId !== null ? (string) $messageId : null,
            'event_type' => $eventType !== null ? (string) $eventType : null,
            'sender_type' => $senderType !== null ? (string) $senderType : null,
            'message_content' => $messageContent !== null ? (string) $messageContent : null,
            'inbox_id' => $inboxId !== null ? (int) $inboxId : null,
            'message_type' => $messageType !== null ? (string) $messageType : null,
            'message_external_id' => $messageExternalId !== null ? (string) $messageExternalId : null,
            'sender_phone' => $senderPhone !== null ? (string) $senderPhone : null,
        ];
    }
}
