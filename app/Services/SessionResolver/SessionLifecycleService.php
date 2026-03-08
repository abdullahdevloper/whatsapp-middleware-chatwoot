<?php

namespace App\Services\SessionResolver;

use App\Models\ConversationSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

class SessionLifecycleService
{
    public function handle(
        int $tenantId,
        ?int $conversationId,
        ?int $inboxId,
        ?string $senderType,
        ?string $messageContent
    ): array {
        if ($conversationId === null) {
            return ['action' => 'no_conversation'];
        }

        $now = CarbonImmutable::now();
        $session = ConversationSession::query()
            ->where('tenant_id', $tenantId)
            ->where('chatwoot_conversation_id', $conversationId)
            ->where('inbox_id', $inboxId)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($senderType === 'agent') {
            if ($session !== null) {
                $session->status = 'paused_by_agent';
                $session->paused_reason = 'agent_override';
                $session->paused_at = $now;
                $session->save();
            }

            return ['action' => 'paused_by_agent'];
        }

        $isStart = $this->isStartTrigger($messageContent);

        if ($session === null) {
            return $this->startNewSessionSafely($tenantId, $conversationId, $inboxId, $now, 'started_new');
        }

        if ($session->status === 'paused_by_agent') {
            if ($isStart) {
                return $this->startNewSessionSafely($tenantId, $conversationId, $inboxId, $now, 'restarted');
            }

            return ['action' => 'paused_blocked'];
        }

        if ($session->status === 'expired') {
            if ($isStart) {
                return $this->startNewSessionSafely($tenantId, $conversationId, $inboxId, $now, 'restarted');
            }

            if ($session->expired_notified_at === null) {
                $session->expired_notified_at = $now;
                $session->save();
                return ['action' => 'expired_notified'];
            }

            return ['action' => 'expired_blocked'];
        }

        if ($session->status === 'active') {
            if ($session->expires_at !== null && $session->expires_at->lessThanOrEqualTo($now)) {
                $session->status = 'expired';
                if ($session->expired_notified_at === null) {
                    $session->expired_notified_at = $now;
                    $session->save();
                    return ['action' => 'expired_notified'];
                }
                $session->save();
                return ['action' => 'expired_blocked'];
            }

            return ['action' => 'active'];
        }

        if ($session->status === 'closed') {
            if ($isStart) {
                return $this->startNewSessionSafely($tenantId, $conversationId, $inboxId, $now, 'restarted');
            }

            return ['action' => 'closed_blocked'];
        }

        return ['action' => 'ignored'];
    }

    private function isStartTrigger(?string $messageContent): bool
    {
        if ($messageContent === null) {
            return false;
        }

        return strtoupper(trim($messageContent)) === 'START';
    }

    private function startNewSessionSafely(
        int $tenantId,
        int $conversationId,
        ?int $inboxId,
        CarbonImmutable $now,
        string $action
    ): array {
        try {
            $newSession = $this->createNewSession($tenantId, $conversationId, $inboxId, $now);
            return ['action' => $action, 'session_id' => $newSession->id];
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new ActiveSessionConflict($tenantId, $conversationId, $inboxId);
            }

            throw $e;
        }
    }

    private function createNewSession(int $tenantId, int $conversationId, ?int $inboxId, CarbonImmutable $now): ConversationSession
    {
        ConversationSession::query()
            ->where('tenant_id', $tenantId)
            ->where('chatwoot_conversation_id', $conversationId)
            ->where('inbox_id', $inboxId)
            ->where('status', 'active')
            ->update(['status' => 'closed']);

        return ConversationSession::query()->create([
            'tenant_id' => $tenantId,
            'chatwoot_conversation_id' => $conversationId,
            'inbox_id' => $inboxId,
            'status' => 'active',
            'paused_reason' => null,
            'paused_at' => null,
            'expires_at' => null,
            'expired_notified_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23505';
    }
}
