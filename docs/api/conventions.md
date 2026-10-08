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

## Phase 2 Audit reads

Owner Audit uses `GET /api/v1/shop/audit-events` and `GET /api/v1/shop/audit-events/{public_id}`. Tenant/Shop authority is server-derived. Normal Platform Audit uses the equivalent `/api/v1/platform/audit-events` GET routes and cannot accept Tenant/Shop scope. Tenant-support Audit uses POST `/api/v1/platform/audit-events/support/query` and POST `/api/v1/platform/audit-events/support/events/{public_id}` so the required reason and scope do not enter URLs, browser history, or routine access logs.

Audit collections use stable keyset pagination with an opaque `next_cursor`, not offset pagination. Filters are bounded to date range, category, action, actor, resource type/public UUID, and canonical correlation UUID. Page sizes are 1–50. Invalid filters and cursors use `AUDIT_FILTER_INVALID` and `AUDIT_PAGE_INVALID`; foreign or unavailable events use the non-enumerating `AUDIT_EVENT_NOT_FOUND`. Audit requests share identity-scoped throttle buckets and return `RATE_LIMITED` with `Retry-After` when exhausted.

## Phase 3 WP1 Product and stock resources

Authenticated Shop routes provide paginated Product search/list, Product detail/create/edit/explicit deactivation, and explicit low-stock-threshold update. External identity is Product UUID; Stock Position detail is addressed through its Product. Protected quantity/cost/state/pointer fields have no generic route. Stable conflicts include `PRODUCT_UNCHANGED`, `PRODUCT_ALREADY_INACTIVE`, `PRODUCT_UNIT_IMMUTABLE`, and `THRESHOLD_UNCHANGED`.

## Phase 3 WP2 Intake resources

Explicit authenticated Shop routes support Intake list/detail, Draft create/header edit, item add/edit/remove, Draft discard, and `/post`. Generic status, movement-create, position-update and posted-evidence delete routes do not exist. Stable conflicts include `INTAKE_ALREADY_POSTED`, `OPENING_ALREADY_ESTABLISHED`, `INVENTORY_STATE_CONFLICT`, `PRODUCT_INACTIVE`, and `INTAKE_LINE_CONFLICT`.
