<?php

namespace App\Services\FlowEngine;

use App\Models\ConversationSession;
use App\Models\Flow;
use App\Services\ChatwootClient\ChatwootClient;
use App\Services\Observability\Metrics;
use App\Services\FlowValidator\FlowValidator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
// use Illuminate\Container\Attributes\Log;
use Illuminate\Support\Facades\Log;

class FlowEngine
{
    public function __construct(private readonly ChatwootClient $chatwoot)
    {
    }

    public function handle(
        int $tenantId,
        int $accountId,
        int $conversationId,
        ?string $messageContent,
        ?string $flowKey,
        ?int $inboxId,
        ?string $messageId
    ): void {
        try {
            $session = ConversationSession::query()
                ->where('tenant_id', $tenantId)
                ->where('chatwoot_conversation_id', $conversationId)
                ->where('status', 'active')
                ->orderByDesc('id')
                ->first();

            if ($session === null) {
                Log::info('No active session for tenant_id: ' . $tenantId . ' conversation_id: ' . $conversationId);
                return;
            }

            $now = CarbonImmutable::now();

            $definition = $this->loadFlowDefinition($tenantId, $flowKey, $conversationId, $inboxId, $messageId);
            if (isset($definition['start'], $definition['nodes']) && is_array($definition['nodes'])) {
                $validator = new FlowValidator();
                $errors = $validator->validate($definition);
                if (!empty($errors)) {
                    Log::error('flow_validation_error', [
                        'tenant_id' => $tenantId,
                        'conversation_id' => $conversationId,
                        'inbox_id' => $inboxId,
                        'message_id' => $messageId,
                        'errors' => $errors,
                    ]);
                    $this->chatwoot->sendMessage(
                        $accountId,
                        $conversationId,
                        'تم استلام رسالتك بنجاح. النظام قيد الاختبار.',
                        $tenantId,
                        $inboxId,
                        $messageId
                    );
                    return;
                }

                $this->executeNodeFlow($session, $definition, $messageContent, $tenantId, $accountId, $conversationId, $inboxId, $messageId, $now);
                return;
            }

            $flowType = $definition['type'] ?? 'hardcoded';

            if ($session->state_key === null) {
                $session->state_key = 'menu';
                $session->state_payload = null;
                $session->updated_at = $now;
                $session->save();

                Log::info('flow_step_executed', [
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversationId,
                    'inbox_id' => $inboxId,
                    'message_id' => $messageId,
                    'node_id' => 'menu',
                    'node_type' => 'menu',
                ]);
                Metrics::increment('flows_executed_total');

                $this->chatwoot->sendMessage(
                    $accountId,
                    $conversationId,
                    $this->menuText($flowType, $flowKey, $definition),
                    $tenantId,
                    $inboxId,
                    $messageId
                );
                return;
            }

            Log::info('Session state_key: ' . $session->state_key . ' for tenant_id: ' . $tenantId . ' conversation_id: ' . $conversationId);
            if ($session->state_key === 'menu') {
                $selection = trim((string) $messageContent);
                if (!in_array($selection, ['1', '2'], true)) {
                    Log::info('Invalid selection for tenant_id: ' . $tenantId . ' conversation_id: ' . $conversationId);
                    $this->chatwoot->sendMessage(
                        $accountId,
                        $conversationId,
                        "الرجاء اختيار رقم صحيح: 1 أو 2",
                        $tenantId,
                        $inboxId,
                        $messageId
                    );
                    return;
                }

                $session->state_key = 'details';
                $session->state_payload = ['selection' => $selection];
                $session->updated_at = $now;
                $session->save();

                $details = $this->detailsText($flowType, $flowKey, $definition, $selection);
                Log::info('flow_step_executed', [
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversationId,
                    'inbox_id' => $inboxId,
                    'message_id' => $messageId,
                    'node_id' => 'details',
                    'node_type' => 'message',
                ]);
                Metrics::increment('flows_executed_total');
                $this->chatwoot->sendMessage(
                    $accountId,
                    $conversationId,
                    $details,
                    $tenantId,
                    $inboxId,
                    $messageId
                );

                Log::info('Flow handling completed for tenant_id: ' . $tenantId . ' conversation_id: ' . $conversationId);
                return;
            }

            Log::info('Fallback reply for tenant_id: ' . $tenantId . ' conversation_id: ' . $conversationId);
            $this->chatwoot->sendMessage(
                $accountId,
                $conversationId,
                'تم استلام رسالتك بنجاح. النظام قيد الاختبار.',
                $tenantId,
                $inboxId,
                $messageId
            );
        } catch (\Throwable $e) {
            Metrics::increment('errors_total');
            Log::error('flow_execution_error', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'inbox_id' => $inboxId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function executeNodeFlow(
        ConversationSession $session,
        array $definition,
        ?string $messageContent,
        int $tenantId,
        int $accountId,
        int $conversationId,
        ?int $inboxId,
        ?string $messageId,
        CarbonImmutable $now
    ): void {
        $nodes = $definition['nodes'];
        $start = $definition['start'];

        if ($session->state_key === null) {
            $session->state_key = $start;
            $session->state_payload = $session->state_payload ?? [];
            $session->updated_at = $now;
            $session->save();
        }

        $maxSteps = 10;
        $steps = 0;

        while ($steps < $maxSteps) {
            $steps++;
            $nodeId = $session->state_key;
            $node = $nodes[$nodeId] ?? null;
            if (!is_array($node)) {
                Log::info('Fallback reply for tenant_id: ' . $tenantId . ' conversation_id: ' . $conversationId);
                $this->chatwoot->sendMessage(
                    $accountId,
                    $conversationId,
                    'تم استلام رسالتك بنجاح. النظام قيد الاختبار.',
                    $tenantId,
                    $inboxId,
                    $messageId
                );
                return;
            }

            $nodeType = $node['type'] ?? 'message';

            if ($nodeType === 'menu') {
            if ($messageContent !== null && $session->state_key === $nodeId) {
                $selection = trim($messageContent);
                $options = $node['options'] ?? [];
                if (isset($options[$selection])) {
                    Log::info('menu_match_debug', [
                        'messageContent' => $messageContent,
                        'options' => $options,
                        'matched' => true,
                    ]);
                    Log::info('menu_node_debug', [
                        'state_key_before' => $session->state_key,
                        'messageContent' => $messageContent,
                        'options' => $options,
                        'matched' => true,
                        'state_key_after' => $options[$selection],
                    ]);
                    $session->state_key = $options[$selection];
                    $session->updated_at = $now;
                    $session->save();
                    return;
                }
            }

            $text = $this->interpolateVariables($node['text'] ?? 'اختر:', $session->state_payload);
            Log::info('menu_match_debug', [
                'messageContent' => $messageContent,
                'options' => $node['options'] ?? [],
                'matched' => false,
            ]);
            Log::info('menu_node_debug', [
                'state_key_before' => $session->state_key,
                'messageContent' => $messageContent,
                'options' => $node['options'] ?? [],
                'matched' => false,
                'state_key_after' => $session->state_key,
            ]);
            Log::info('flow_step_executed', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'inbox_id' => $inboxId,
                'message_id' => $messageId,
                'node_id' => $nodeId,
                'node_type' => 'menu',
            ]);
            Metrics::increment('flows_executed_total');
            $this->chatwoot->sendMessage(
                $accountId,
                $conversationId,
                $text,
                $tenantId,
                $inboxId,
                $messageId
            );
                return;
        }

            if ($nodeType === 'message') {
                $text = $this->interpolateVariables($node['text'] ?? '', $session->state_payload);
                $next = $node['next'] ?? null;
                Log::info('flow_step_executed', [
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversationId,
                    'inbox_id' => $inboxId,
                    'message_id' => $messageId,
                    'node_id' => $nodeId,
                    'node_type' => 'message',
                ]);
                Metrics::increment('flows_executed_total');
                $this->chatwoot->sendMessage(
                    $accountId,
                    $conversationId,
                    $text,
                    $tenantId,
                    $inboxId,
                    $messageId
                );
                if (is_string($next) && $next !== '') {
                    $session->state_key = $next;
                    $session->updated_at = $now;
                    $session->save();
                    continue;
                }
                return;
            }

            if ($nodeType === 'input') {
            $key = $node['key'] ?? $nodeId;
            $next = $node['next'] ?? null;
            $payload = is_array($session->state_payload) ? $session->state_payload : [];

            // First entry to input node: prompt only, no value capture.
            if ($messageContent === null || $messageContent === '' || $session->state_key === $nodeId) {
                $prompt = $node['text'] ?? 'أرسل القيمة المطلوبة.';
                Log::info('flow_step_executed', [
                    'tenant_id' => $tenantId,
                    'conversation_id' => $conversationId,
                    'inbox_id' => $inboxId,
                    'message_id' => $messageId,
                    'node_id' => $nodeId,
                    'node_type' => 'input',
                ]);
                Metrics::increment('flows_executed_total');
                $this->chatwoot->sendMessage(
                    $accountId,
                    $conversationId,
                    $prompt,
                    $tenantId,
                    $inboxId,
                    $messageId
                );
                return;
            }

            // Second message: save value and move to next node.
            $payload[$key] = trim($messageContent);
            $session->state_payload = $payload;
            if (is_string($next) && $next !== '') {
                $session->state_key = $next;
            }
            $session->updated_at = $now;
            $session->save();

            Log::info('flow_variable_saved', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'node_id' => $nodeId,
                'variable_key' => $key,
            ]);

            Log::info('flow_step_executed', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'inbox_id' => $inboxId,
                'message_id' => $messageId,
                'node_id' => $nodeId,
                'node_type' => 'input',
            ]);
            Metrics::increment('flows_executed_total');
                return;
        }

            if ($nodeType === 'action') {
                $action = $node['action'] ?? null;
                $params = is_array($node['params'] ?? null) ? $node['params'] : [];
                $next = $node['next'] ?? null;

            $payload = is_array($session->state_payload) ? $session->state_payload : [];
            if ($action === 'save_variable') {
                $key = $params['key'] ?? null;
                $value = $params['value'] ?? null;
                if ($key !== null) {
                    $payload[$key] = $value;
                    Log::info('flow_variable_saved', [
                        'tenant_id' => $tenantId,
                        'conversation_id' => $conversationId,
                        'node_id' => $nodeId,
                        'variable_key' => $key,
                    ]);
                }
            }

            if (is_string($next) && $next !== '') {
                $session->state_key = $next;
            }
            $session->state_payload = $payload;
            $session->updated_at = $now;
            $session->save();

            $this->executeAction(
                $action,
                $params,
                $session->state_payload,
                $tenantId,
                $accountId,
                $conversationId,
                $inboxId,
                $messageId
            );

            Log::info('flow_action_executed', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'inbox_id' => $inboxId,
                'node_id' => $nodeId,
                'action' => $action,
            ]);

            if (is_string($next) && $next !== '') {
                continue;
            }
            return;
        }

            if ($nodeType === 'condition') {
            $variable = $node['variable'] ?? null;
            $operator = $node['operator'] ?? null;
            $value = $node['value'] ?? null;
            $trueNode = $node['true'] ?? null;
            $falseNode = $node['false'] ?? null;

            $payload = is_array($session->state_payload) ? $session->state_payload : [];
            $current = $variable !== null ? ($payload[$variable] ?? null) : null;

            $result = $this->evaluateCondition($current, $operator, $value);

            $session->state_key = $result ? $trueNode : $falseNode;
            $session->updated_at = $now;
            $session->save();

            Log::info('flow_condition_evaluated', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'inbox_id' => $inboxId,
                'node_id' => $nodeId,
                'result' => $result,
            ]);

                continue;
        }

            return;
        }
    }

    private function evaluateCondition($current, ?string $operator, $value): bool
    {
        return match ($operator) {
            'equals' => (string) $current === (string) $value,
            'not_equals' => (string) $current !== (string) $value,
            'contains' => is_string($current) && $value !== null && str_contains($current, (string) $value),
            'exists' => $current !== null && $current !== '',
            default => false,
        };
    }

    private function executeAction(
        ?string $action,
        array $params,
        $payload,
        int $tenantId,
        int $accountId,
        int $conversationId,
        ?int $inboxId,
        ?string $messageId
    ): void {
        if ($action === 'log_event') {
            Log::info($params['event'] ?? 'flow_action_log_event', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'inbox_id' => $inboxId,
                'message_id' => $messageId,
            ]);
            return;
        }

        if ($action === 'save_variable') {
            return;
        }

        if ($action === 'call_webhook') {
            $url = $params['url'] ?? null;
            $body = is_array($params['body'] ?? null) ? $params['body'] : [];
            if (!empty($url)) {
                Http::asJson()->post($url, $body);
            }
            return;
        }

        if ($action === 'send_message') {
            $text = $this->interpolateVariables((string) ($params['text'] ?? ''), $payload);
            $this->chatwoot->sendMessage(
                $accountId,
                $conversationId,
                $text,
                $tenantId,
                $inboxId,
                $messageId
            );
            return;
        }

        if ($action === 'assign_agent') {
            $agentId = $params['agent_id'] ?? null;
            if ($agentId !== null) {
                $this->chatwoot->assignAgent(
                    $accountId,
                    $conversationId,
                    (int) $agentId,
                    $tenantId,
                    $inboxId,
                    $messageId
                );
            }
            return;
        }

        if ($action === 'add_label') {
            $label = $params['label'] ?? null;
            if ($label !== null) {
                $this->chatwoot->addLabel(
                    $accountId,
                    $conversationId,
                    (string) $label,
                    $tenantId,
                    $inboxId,
                    $messageId
                );
            }
            return;
        }

        if ($action === 'resolve_conversation') {
            $this->chatwoot->resolveConversation(
                $accountId,
                $conversationId,
                $tenantId,
                $inboxId,
                $messageId
            );
            return;
        }

        if ($action === 'send_private_note') {
            $text = $this->interpolateVariables((string) ($params['text'] ?? ''), $payload);
            $this->chatwoot->sendPrivateNote(
                $accountId,
                $conversationId,
                $text,
                $tenantId,
                $inboxId,
                $messageId
            );
            return;
        }
    }

    private function interpolateVariables(string $text, $payload): string
    {
        if (!is_array($payload) || $text === '') {
            return $text;
        }

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($matches) use ($payload) {
            $key = $matches[1];
            return isset($payload[$key]) ? (string) $payload[$key] : $matches[0];
        }, $text);
    }

    private function loadFlowDefinition(int $tenantId, ?string $flowKey, int $conversationId, ?int $inboxId, ?string $messageId): array
    {
        if (empty($flowKey)) {
            Log::info('flow_loaded_from_code', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'inbox_id' => $inboxId,
                'message_id' => $messageId,
                'flow_key' => $flowKey,
            ]);
            return ['type' => 'hardcoded'];
        }

        $flow = Flow::query()
            ->where('tenant_id', $tenantId)
            ->where('flow_key', $flowKey)
            ->first();

        if ($flow === null) {
            Log::info('flow_loaded_from_code', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'inbox_id' => $inboxId,
                'message_id' => $messageId,
                'flow_key' => $flowKey,
            ]);
            return ['type' => 'hardcoded'];
        }

        Log::info('flow_loaded_from_db', [
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'inbox_id' => $inboxId,
            'message_id' => $messageId,
            'flow_key' => $flowKey,
            'flow_id' => $flow->id,
        ]);

        $definition = $flow->definition_json;
        if (is_string($definition)) {
            $decoded = json_decode($definition, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_array($definition)) {
            return $definition;
        }

        return ['type' => 'hardcoded'];
    }

    private function menuText(string $flowType, ?string $flowKey, array $definition): string
    {
        if ($flowType === 'menu' && !empty($definition['menu_text'])) {
            return (string) $definition['menu_text'];
        }

        return match ($flowKey) {
            'equipment_flow' => "اختر رقم المعدات:\n1) معدة A\n2) معدة B",
            'clothes_flow' => "اختر رقم الملابس:\n1) قطعة A\n2) قطعة B",
            default => "اختر رقم المنتج:\n1) منتج A\n2) منتج B",
        };
    }

    private function detailsText(string $flowType, ?string $flowKey, array $definition, string $selection): string
    {
        if ($flowType === 'menu' && !empty($definition['details_text'])) {
            $details = $definition['details_text'];
            if (is_array($details) && isset($details[$selection])) {
                return (string) $details[$selection];
            }
            if (is_string($details)) {
                return $details;
            }
        }

        return match ($flowKey) {
            'equipment_flow' => $selection === '1'
                ? "تفاصيل معدة A: وصف مختصر."
                : "تفاصيل معدة B: وصف مختصر.",
            'clothes_flow' => $selection === '1'
                ? "تفاصيل قطعة A: وصف مختصر."
                : "تفاصيل قطعة B: وصف مختصر.",
            default => $selection === '1'
                ? "تفاصيل منتج A: وصف مختصر."
                : "تفاصيل منتج B: وصف مختصر.",
        };
    }
}
