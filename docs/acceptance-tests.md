# Acceptance Tests

Scope: Validate current Middleware behavior (no new logic). Tests are written against the existing flow:
Webhook → Tenant Resolution → Idempotency → SessionLifecycle → Channel Resolution → FlowEngine → ChatwootClient.

## Webhook Ingestion

1. Valid Webhook Accepted
- Given a well-formed JSON webhook with a known Chatwoot account.
- When POSTed to `POST /api/webhooks/chatwoot`.
- Then the response is 200 with status `accepted`.

2. Invalid JSON Rejected
- Given a malformed JSON payload.
- When POSTed to `POST /api/webhooks/chatwoot`.
- Then the response is 400 with `Invalid JSON`.

3. Unknown Tenant Rejected
- Given a valid payload with `account_id` not mapped to any tenant.
- When POSTed.
- Then the response is 404 with `Unknown tenant`.

## Idempotency (Duplicate Events)

1. Duplicate Webhook Ignored
- Given two identical raw webhook payloads for the same tenant.
- When POSTed twice.
- Then the second response is 200 with status `duplicate_ignored`.
- And no additional session side effects are created.

## Tenant Isolation

1. Tenant Boundary Enforcement
- Given two tenants with different Chatwoot account IDs.
- When a webhook arrives for tenant A.
- Then only tenant A data is read/updated.
- And no sessions or logs are created under tenant B.

2. Cross-Tenant Duplicate Protection
- Given identical raw payloads sent to two different tenants.
- When processed independently.
- Then each tenant maintains its own idempotency boundary.

## Session Lifecycle

1. Agent Override
- Given an active session for a conversation.
- When a webhook with sender type `agent` arrives.
- Then the session is marked `paused_by_agent`.
- And subsequent non-START messages do not trigger Flow.

2. Strict START Resume
- Given a session in `paused_by_agent`.
- When a webhook with content `START` arrives.
- Then a new active session is created for the same conversation.
- And the previous active session is closed.

3. Expired Session Notification (Once)
- Given an active session with `expires_at` in the past and `expired_notified_at` is null.
- When any non-START message arrives.
- Then the session becomes `expired` and `expired_notified_at` is set.
- And no further expiry notification is sent on subsequent messages.

4. Expired Session Restart
- Given a session in `expired`.
- When a webhook with content `START` arrives.
- Then a new active session is created for the same conversation.
- And the previous session remains for history.

5. Concurrent START (Unique Constraint)
- Given two concurrent `START` events for the same conversation.
- When both are processed.
- Then only one active session exists due to the unique index.
- And the losing request returns `active_exists` without 500.

## Unknown Inbox Handling

1. Missing Inbox Mapping
- Given a webhook with an `inbox_id` not present for the tenant.
- When processed.
- Then the flow runs with default behavior (no inbox-specific flow).

## Channel-Based Flow Routing

1. Inbox Flow Mapping
- Given a tenant with two inboxes, each configured with a different `flow_key`.
- When a message arrives via inbox A.
- Then FlowEngine uses the flow mapped to inbox A.
- When a message arrives via inbox B.
- Then FlowEngine uses the flow mapped to inbox B.

2. Missing Inbox Mapping Fallback
- Given a webhook with an inbox not mapped in `chatwoot_inboxes`.
- When processed.
- Then FlowEngine falls back to the default flow behavior.

## Agent Override Persistence

1. Pause Persists Across Messages
- Given a conversation session set to `paused_by_agent`.
- When multiple non-START messages arrive.
- Then no flow is executed for any of those messages.
- And the session remains `paused_by_agent`.

## ChatwootClient Messaging

1. Outgoing Message Payload
- Given a FlowEngine step that triggers a reply.
- When ChatwootClient sends the message.
- Then the request includes:
  - `content`
  - `message_type = outgoing`
  - `private = false`

2. Conversation Consistency
- Given an incoming webhook with `conversation_id`.
- When a response is sent.
- Then the outgoing message uses the same `conversation_id`.

## Chatwoot API Failure Handling

1. Outgoing Send Failure
- Given Chatwoot API is unavailable or returns an error.
- When ChatwootClient attempts to send a message.
- Then the failure is surfaced in logs.
- And no retry is attempted by the Middleware.
