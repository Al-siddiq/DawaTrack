# HTTP API conventions

APIs are versioned under `/api/v1`, use JSON, and require `Accept: application/json`. Successful and failed responses share this shape:

```json
{"success":true,"data":{},"meta":{"request_id":"..."},"error":null}
```

```json
{"success":false,"data":null,"meta":{"request_id":"..."},"error":{"code":"STABLE_CODE","message":"Safe message","details":{}}}
```

Validation details map field names to arrays of safe messages. Never expose SQL, paths, stack traces, secrets, or internal exception messages. Clients may send a canonical UUIDv4 `X-Request-ID`; any missing, malformed, non-v4, or noncanonical value is replaced with one server-generated lowercase UUIDv4. The same identifier appears in the response header, `meta.request_id`, relevant logs, and Audit evidence for that operation.

Protected/finalized evidence has no generic update or delete endpoint. Future correction endpoints are explicit domain actions, retain the original, require the domain permission/reason, use domain source/reversal links and locks, and add Audit causality. Before Phase 10, repeat safety is domain-specific: UUIDs, unique constraints, conditional transitions, locks, and specific `409` conflict codes. There is no generic `DUPLICATE_OPERATION`, `idempotency_key`, durable request replay, or Sync Receipt behavior.

Phase 2 exposes no change pull, watermark, cursor, acknowledgement, or replay endpoint. Change publication and watermark acquisition are internal owning-service contracts.

Future collections use `meta.pagination` with `page`, `per_page`, `total`, and `total_pages`. `per_page` is server-capped. The centralized browser client supplies same-origin credentials, CSRF headers on writes, request IDs, JSON normalization, cancellation/timeouts, and stable client errors.
