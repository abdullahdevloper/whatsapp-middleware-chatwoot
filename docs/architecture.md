# Architecture Notes

This document reflects the current runtime behavior of the Middleware.

## Webhook Processing (Runtime)

1. Webhook received at `POST /api/webhooks/chatwoot`.
2. Raw JSON parsed and normalized.
3. Test mode filter:
   - If enabled, only the configured test phone is processed.
4. Message direction filter:
   - Only incoming messages from end users are processed.
5. Tenant resolution by Chatwoot account ID.
6. Idempotency:
   - `event_uid` uses a hash derived from message external ID plus event/message attributes if present.
   - If external ID is absent, it falls back to a hash derived from message UID plus event/message attributes.
   - Final fallback is raw payload hash.
7. Session lifecycle decision inside DB transaction.
8. FlowEngine runs only for allowed session states.
9. ChatwootClient sends outgoing messages to Chatwoot API.

## Message Direction Filtering

Only incoming user messages are eligible for processing. Outgoing messages, agent messages, and non-message events are ignored to prevent loops.

## Idempotency

Primary key for idempotency is a hash built from message external ID + event/message attributes. If absent, the system falls back to a hash built from message UID + event/message attributes, then raw payload hash.

## Test Mode

When enabled, only messages from a configured phone number are processed. All others are ignored.

## Outgoing Reliability

Outgoing API calls to Chatwoot use limited retries for transient failures (timeouts, 5xx responses, or 429).
