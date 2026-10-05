# Audit vocabulary and context contract

Phase 1 persists immutable Audit evidence before sensitive mutations. Phase 2 WP1 hardens that writer; it does not add Audit queries, `sync_changes`, Devices, or offline transport.

## Typed append boundary

Every application append uses `AuditAppendCommand`. The command contains an immutable `AuditActor`, approved action/category/resource values, optional resource UUID, policy-controlled reason, bounded metadata, optional causal Audit ID, and validated occurrence/operation context. `AuditService::append()` is the only application write boundary; there is no legacy array writer and no Audit update/delete method.

Authority is explicit:

- `TENANT` requires an active same-Tenant membership, authenticated user, Tenant, and Shop;
- `PLATFORM` requires a Shield `platform_admin`, has no Tenant membership, and may identify the target Tenant/Shop affected by an authorized Platform operation;
- `IDENTITY` is limited to the authenticated user's own security evidence without Tenant scope;
- `SYSTEM` has no user or membership and may carry a validated target scope for scheduled system work.

Target Shop scope is verified against its Tenant. Invalid authority and causal combinations fail closed with non-enumerating errors. Controller payloads never establish actor or authority.

## Canonical correlation

Each HTTP request has one canonical lowercase UUIDv4. `RequestContext` accepts only a UUIDv4 inbound `X-Request-ID`; otherwise it creates one. The same value is used in the response `X-Request-ID`, API `meta.request_id`, relevant logs, and `audit_events.correlation_id`. Audit never creates a replacement correlation ID.

Spark commands initialize one correlation UUID at command entry and reuse it in job logs and every Audit append made by that command.

## Device and operation boundary

`actor_device_id` remains `NULL` throughout Phase 2. No browser/client device identifier is accepted or trusted. Registered Device attribution and the FK remain deferred until Phase 7. A validated UUIDv4 `sync_operation_id` may be recorded only as future-compatible causal context; it does not provide replay, authorization, Device identity, or offline eligibility.

## Reason policy

Reasons are trimmed and limited to 500 characters. Security, authorization, domain, onboarding, subscription, and lifecycle actions require a nonblank reason. The current mutable profile/branding/asset actions retain their approved optional-reason behavior. The service never fabricates a reason.

## Metadata safety and output

Audit metadata is purposeful minimized evidence, never a request-body dump. The recursive policy:

- rejects forbidden credential, password, MFA, recovery, verification, token, cookie, session, TLS-key, request-body, and raw binary/image keys;
- permits only arrays and scalar values;
- permits at most four nested levels and 40 entries per array;
- rejects strings over 500 characters;
- rejects serialized JSON over 16,384 bytes.

Unsafe metadata aborts the owning transaction. `AuditOutputMapper` re-applies the policy when constructing immutable `AuditEvent` output and omits storage-only IP/user-agent fields from that DTO.

## Occurrence, creation, and causality

`created_at` is always the server insertion time. Existing online operations use that server instant as `occurred_at`. The typed occurrence value supports an explicitly supplied UTC `DATETIME(6)` only within the bounded contract (no more than five minutes ahead or approximately one year behind); it does not establish offline trust.

`caused_by_audit_event_id` must refer to an existing event with exactly compatible Tenant/Shop scope. This relationship supplies accountability context only; future protected domains still require their own source/reversal foreign keys, locks, and invariants.

## Atomicity and append-only operation

Business mutation and required Audit remain in the owning service transaction. Audit validation or insertion failure rolls back the business mutation. The guarded pre-schema MFA bootstrap behavior is the sole installation-time exception.

From Phase 2 WP2, qualifying projection/configuration mutations append required `sync_changes` after Audit and before commit. Business, Audit, and all sibling changes share one transaction and canonical correlation. Audit-only security actions do not emit change rows.

Production runtime credentials receive `SELECT, INSERT` but not `UPDATE` or `DELETE` on `audit_events`; see `docs/operations/phase-01-subscriptions-and-lifecycle.md`.
