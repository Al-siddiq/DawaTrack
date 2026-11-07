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

Replacement Intake Draft creation, editing and discard are non-authoritative and emit no permanent Audit. The bounded mandatory replacement reason is retained with the Draft. A successful replacement post atomically emits both `stock_intake.posted` and reasoned `stock_intake.replaced`; a rejected or rolled-back post emits neither success event.

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

## Phase 2 query and support boundary

WP3 exposes immutable, redacted Audit reads through the same `AuditOutputMapper` used by the typed contract. Primary Owner reads derive Tenant and current Shop from `ShopContext`; they include current-Shop and Tenant-wide evidence and never accept Tenant/Shop authority parameters. Normal Platform reads require `platform.audit`, the `platform_admin` group, and recent MFA, and are limited to platform-scoped (`tenant_id IS NULL`) evidence.

Tenant diagnostic reading is a separate POST-only support workflow. It requires `platform.audit_support`, recent MFA, an explicit server-resolved Tenant/Shop pair, and a nonblank bounded reason. Before results are returned, `SupportAuditAccessService` commits a `SUPPORT` / `support.audit_accessed` event carrying the Platform actor, target scope, canonical correlation, reason, and minimized purpose metadata. A short-lived continuation is the event's public UUID, bound to that operator and revalidated on every request; it is not a support session, membership, login, or impersonation. A failed evidence append returns no scoped results. Routine pagination and detail reads create neither Audit nor `sync_changes`.

List ordering is `occurred_at DESC, id DESC`; opaque cursors contain only the last event's occurrence time and public UUID, which is resolved again inside the authorized scope. Page size is capped at 50 and date ranges at 366 days. Detail and causal/correlation results are re-scoped before release. Internal numeric IDs, IP bytes, user-agent hashes, device identity, and unredacted metadata are never returned. There is no Audit mutation endpoint.

## Phase 3 WP1 vocabulary

WP1 adds INVENTORY actions `product.created`, `product.updated`, `product.deactivated`, and `stock.threshold_updated`, with resources `product` and `stock_position`. Create/edit/threshold reasons are optional; deactivation requires a reason. Rejected validation/conflict creates no event. Each successful event shares the business transaction and request correlation.

## Phase 3 WP2

Ordinary Draft Intake CRUD/discard emits no permanent Audit. Atomic posting emits one optional-reason `stock_intake.posted` INVENTORY event on resource `stock_intake`, with bounded kind/line-count/rounded-total/occurrence metadata. Movement rows are the line evidence.

## Phase 3 WP3 vocabulary

All WP3 actions require a reason: `stock_movement.recorded`, `stock_movement.corrected`, `stock_movement.reversed`, `stock_intake.reversed`, and `stock_intake.replaced`. Resources remain `stock_movement`/`stock_intake`, category INVENTORY. Metadata is bounded to public causal IDs, movement type, signed quantity and safe before/after/cost snapshots. Domain FKs, not Audit metadata, are causal authority.

Phase 3 closure confirms Product create/update/deactivate, threshold, Intake post, manual/Correction, Movement reversal, Intake reversal, and replacement post append typed INVENTORY Audit inside the owning transaction. Ordinary and replacement Draft lifecycle remains silent; diagnostics append nothing because they only read.


## Phase 4 WP1 Opening Cash

Closed vocabulary adds `reconciliation.opening_started`, `.opening_counted`, `.opening_committed`, `.opening_reviewed`, `.opening_finalized`, `.opening_aborted` under RECONCILIATION with `reconciliation_session` resource. Each authoritative transition appends Audit and aggregate change atomically. Reasons required; GET and exact terminal retries create no event. Credential reset uses bounded `access_invalidated` metadata; sensitive session/token keys remain forbidden.

## Phase 4 WP2

Customer lifecycle uses `customer.created`, `customer.updated`, `customer.deactivated`, category DEBT/resource `customer`. Metadata lists affected field names rather than phone/notes payloads. Fixed create/update reasons describe ordinary actions; deactivation requires an operator reason. Saved-draft discard is material: `sale.draft_discarded`, category SALE/resource `sale`, required reason, line count and estimated total. Ordinary draft create/edit/read and payment preview append no business evidence. Customer writes and discard Audit are atomic with their mutations; failed/rejected commands do not report successful events. The full contract is in `docs/modules/phase-04-wp2.md`.

## Phase 4 WP3

`sale.posted`, SALE category/resource sale, records one aggregate forward transaction with actor, fixed reason, total/paid/credit, line count, historical total COGS and unestablished-cost line count. Business FKs link immutable payments/Receivable and each Sale Item movement; correlation groups effects without replacing those relationships. Posting Audit and all changes commit with header/items/stock/payment/credit; any failure rolls back the entire command. Repeating the same posted UUID and read queries append no false success. Cost metadata is part of existing Owner/authorized Audit visibility, not a new Staff cost read route. See `docs/modules/phase-04-wp3.md`.


## WP4-B — 2026-11-05

`sale.return_posted` is an aggregate SALE/sale action with required reason, original Sale public identity, Return value, refund and debt-reduction totals. Return draft discard reuses sale.draft_discarded with kind RETURN. Posting Audit failure rolls back stock/money/debt/header/change effects. Draft create/save has no financial effects.
