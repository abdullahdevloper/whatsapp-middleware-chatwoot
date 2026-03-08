<?php

namespace App\Services\SessionResolver;

use App\Models\ConversationSession;

class SessionResolver
{
    public function lockActiveSession(int $tenantId, int $conversationId): ?ConversationSession
    {
        return ConversationSession::query()
            ->where('tenant_id', $tenantId)
            ->where('chatwoot_conversation_id', $conversationId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();
    }
}
