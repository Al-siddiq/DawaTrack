# Phase 2 server change-sequence boundary

`sync_changes` is compact notification/index evidence. It stores generation, scope, resource identity, `UPSERT`/`DEACTIVATE`, server time, and optional operation/correlation causality. It stores no business payload, actor, Device, response, cursor, receipt, or replay outcome.

## Normative publication lock order

Every publisher uses its owning business transaction and the same database connection:

1. obtain all domain/advisory/row locks;
2. mutate and validate the business state;
3. append required Audit;
4. lock the sole active `sync_generations` row with `FOR UPDATE`;
5. acquire no later domain lock;
6. insert all sibling change rows;
7. commit or roll back, releasing the generation lock.

`AUTO_INCREMENT` allocates unique numbers but does not guarantee commit order. The active-generation lock serializes publication: a later publisher cannot allocate until the earlier holder commits or rolls back. Rollback gaps are harmless.

## Watermark

After obtaining its domain locks, the caller locks the active generation and uses a current locking read:

```sql
SELECT change_sequence
FROM sync_changes
WHERE sync_generation_id = ?
ORDER BY change_sequence DESC
LIMIT 1
FOR UPDATE;
```

The result is `(generation_uuid, change_sequence)`, with sequence zero for an empty generation. The caller holds the generation lock until the watermark-consuming business evidence commits. This is not a Device cursor, acknowledgement, receipt, or pull API.

## Generation transitions

Initial seeding and any future activation/retirement lock the active-generation selection inside their transaction. No Phase 2 generation-management UI or endpoint exists.

## Phase 3 WP1 publishers

The closed resource registry now includes `product` and `stock_position`. Product creation publishes both resources as `UPSERT`; edit publishes Product `UPSERT`; deactivation publishes Product `DEACTIVATE` without deactivating its position; threshold change publishes position `UPSERT`. The Shop-scoped position notification uses its Product public UUID. No transport, cursor, receipt or pull API is added.

## Phase 3 WP2

Draft Intake activity emits no changes. Successful posting publishes `stock_intake`, every `stock_movement`, and one final `stock_position` per affected Product, after all Product/position locks and Audit. Position identity remains Shop scope plus Product UUID. `stock_intake` and `stock_movement` are now allow-listed; no transport is added.

## Phase 3 WP3 publication

Manual/Correction/movement reversal publishes the new Movement and final position. Intake reversal publishes its same-kind zero-item reversal header, each reversal Movement, each final position, and original Intake UPSERT for derived state. Originals are never deactivated. Replacement Draft create/edit/discard publishes nothing. Successful replacement posting atomically publishes C, each resulting Movement, each final position and original A UPSERT for its changed derived replacement state.

Phase 3 closure adds no transport or publication kind. Read filters, pagination and diagnostics never publish changes. The final publication matrix remains Product/Position at catalog mutations, Intake/Movement/final Position at posting, Movement/Position for manual/reversal, and derived original-Intake UPSERT for Intake reversal/replacement posting.


## Phase 4 WP1 publisher

`reconciliation_session` is an approved aggregate resource; each successful Opening transition publishes one UPSERT. Count confirmation captures active generation/cutoff sequence after existing domain locks and Audit, before its own notification. Review/finalization reject changed generation and preserve the original watermark. No transport, receipt, cursor or replay infrastructure is added.

## Phase 4 WP2 resource extension

Customer lifecycle publishes the approved Tenant-owned `customer` aggregate (`shop_id = null`) with public UUID: UPSERT for create/update, DEACTIVATE for deactivation. Refresh through the scoped Customer list/detail APIs applies the reader's current private-field permission. Publication is in the owning transaction after Customer mutation/Audit; failure rolls everything back. Draft Sale/basket/preparation/discard publishes no sync resource. No payment, Receivable, stock or reconciliation effect occurs. This does not enable a device transport, outbox or generic operation replay. See `docs/modules/phase-04-wp2.md`.

## Phase 4 WP3 resource extension

Approved new resources are `sale` (Sale UUID; lines/actual receipt payments are aggregate children) and `receivable` (Receivable UUID, original Sale credit obligation). Posting publishes these with per-movement `stock_movement` identities, one logical `stock_position` Product UUID per distinct Product, and a Tenant-scoped `customer` context UPSERT when credit is created. There is no arbitrary standalone payment resource or mutable stored Customer balance. Refresh paths are GET `/sales/{uuid}`, `/sale-credit/{uuid}`, existing safe Customer reads and permitted Inventory reads. Publication happens last inside the one posting transaction, after domain effects and Audit. Exact posted repeats publish nothing; drafts/preview remain effect-free. No device/offline/receipt transport is enabled. See [effect matrix](../modules/phase-04-wp3.md).


## WP4-B — 2026-11-05

Posted normal Return refresh publishes original Sale + Return, movement UUIDs, logical Product position UUIDs, original Receivable and relevant Customer context last in the same transaction after Audit. Drafts reserve/publish no financial effects. Same-identity GET recovery handles uncertain responses; offline business replay remains unavailable.


## WP4-C — 2026-11-06

Commercial compensation publishes new/source/root Sale identities, inverse Stock Movements, logical Product positions, original Receivable and Customer context after Audit in one transaction. Replacement preparation has no financial changes; replacement posting refreshes its linked source too. GET correction-state resolves uncertain outcomes without offline replay.


## WP4-D — 2026-11-06

D consumes authoritative record refreshes and normalizes mutation responses via public GET detail. It adds no sync resource or offline command queue. Transient settlement/review resets on reload; financial effects remain exclusively in B/C atomic publication. Draft/replacement preparation is not misreported as payment or credit success.
