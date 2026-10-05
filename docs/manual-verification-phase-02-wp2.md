# Phase 2 WP2 manual verification

Record every item as PASS, FAIL, or BLOCKED. Static inspection is not runtime evidence.

## Schema

1. Apply Phase 1 and seed, then the Phase 2 migration on MySQL 8.4.
2. Compare `SHOW CREATE TABLE sync_changes`, the CI migration, raw SQL, and schema v1.1.
3. Verify the four approved indexes, three parent FKs, kind check, ASCII UUID/correlation columns, InnoDB and collation.
4. Confirm no payload, actor, Device, cursor, receipt, response, version, status, outcome, or idempotency column.
5. Roll back/reapply on a disposable database and verify parent tables remain.

## Locking and watermark concurrency

1. In connection A begin, lock the active generation, and delay commit. Confirm publisher/watermark connection B waits.
2. Insert a lower sequence in A and delay commit. Confirm B cannot allocate/publish a later sequence first.
3. Roll A back; confirm any auto-increment gap is harmless and no row committed.
4. Start a watermark while A is pending. Confirm it waits and includes A after commit.
5. Hold the watermark transaction; confirm later publishers wait.
6. Verify the watermark returns generation UUID plus sequence and uses the documented current locking read.
7. Attempt missing/multiple active generation and transition races; confirm safe rollback/serialization.

## Atomic integration

Exercise onboarding, Staff create/status, permission grant/revoke, Shop settings, branding/asset, every domain transition, subscription/change/expiry, and Tenant/Shop lifecycle. Verify documented resource classification, Tenant/Shop scope, kind, active generation, server time, and identical Audit/change correlation.

For representative operations force business, Audit, generation lookup, and change insert failures independently. Each must leave no partial business/Audit/change state. Repeat and race commands; confirm existing specific `409` behavior and no false evidence.

## Security/UI/non-scope

Attempt cross-Tenant commands and direct unscoped change construction. Confirm non-enumerating denial. Double-click/tap/Enter each touched UI action and verify one server effect; retain fields/reasons after `409`, `422`, timeout, network, and server failure. Repeat at 320/360/390px, tablet, desktop, keyboard-only, and 200% zoom.

Confirm there is no public change/watermark route, sync UI, Device, receipt, cursor, outbox, pull transport, `idempotency_key`, `DUPLICATE_OPERATION`, or durable replay.

Re-run every BLOCKED check from `docs/manual-verification-phase-02-wp1.md` if dependencies, MySQL, grants, and browser access become available.
