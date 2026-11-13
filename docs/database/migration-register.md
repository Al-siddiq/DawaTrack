# Migration and SQL register

DawaTrack Phase 1 uses dependency-focused, one-shot MySQL 8.4 migrations. Each migration issues a fixed list of complete DDL statements; it does not split or interpret an SQL script. The paired SQL file is the authoritative manual review representation and must remain statement-for-statement equivalent to the PHP migration.

## Prerequisite order

1. `CodeIgniter\\Settings` vendor migrations (Shield dependency).
2. `CodeIgniter\\Shield` vendor migrations.
3. `App` migrations below.
4. `PhaseOneSeeder` controlled bootstrap data.

The lock file pins CodeIgniter 4.7.3, Settings 2.4.0, and Shield 1.4.1. Shield 1.4.1 defines `users.id` as `INT UNSIGNED NOT NULL` primary key on InnoDB; every Phase 1 Shield-user FK uses `INT UNSIGNED`. `CreatePhaseOneTenantShop` verifies that physical contract before creating any Shield-dependent table.

| Order | Timestamp | CodeIgniter migration | Equivalent MySQL 8.4 SQL | Tables | Dependency | Rollback scope |
|---:|---|---|---|---|---|---|
| 1 | 2026-09-25-190000 | `CreatePhaseOnePlatformRoots` | `phase-01/20260925_190000_phase_one_platform_roots.sql` | `sync_generations`, `subscription_plans` | MySQL 8.4 | Those two tables |
| 2 | 2026-09-25-190100 | `CreatePhaseOneTenantShop` | `phase-01/20260925_190100_phase_one_tenant_shop.sql` | `tenants`, `shops` | Shield `users` with compatible PK; roots | Those two tables |
| 3 | 2026-09-25-190200 | `CreatePhaseOneMemberships` | `phase-01/20260925_190200_phase_one_memberships.sql` | `tenant_memberships`, `membership_permission_grants` | Tenant/Shop; Shield users | Those two tables |
| 4 | 2026-09-25-190300 | `CreatePhaseOneBrandingDomains` | `phase-01/20260925_190300_phase_one_branding_domains.sql` | `shop_branding_profiles`, `shop_brand_assets`, `shop_domains` | Tenant/Shop; Shield users | Those three tables |
| 5 | 2026-09-25-190400 | `CreatePhaseOneSubscriptions` | `phase-01/20260925_190400_phase_one_subscriptions.sql` | `subscriptions`, `subscription_events` | Plans, Tenant; Shield users | Those two tables |
| 6 | 2026-09-25-190500 | `CreatePhaseOneAudit` | `phase-01/20260925_190500_phase_one_audit.sql` | `audit_events` | Tenant, Shop, memberships; Shield users | Audit table only |
| 7 | 2026-10-04-000000 | `CreatePhaseTwoSyncChanges` | `phase-02/20261004_000000_create_phase_two_sync_changes.sql` | `sync_changes` | Sync Generation, Tenant, scoped Shop | Change sequence table only |

The former monolithic `CreatePhaseOneFoundation` migration and consolidated executable SQL were removed before approval because retaining them would create a duplicate DDL path.

`audit_events.actor_device_id` remains nullable without an FK in Phase 1. Phase 7 adds `fk_audit_events_device` only after `registered_devices` exists.

Phase 2 adds only `sync_changes`. Apply it after the complete Phase 1 App schema. Roll it back only before any later table references it; MySQL DDL remains implicitly committed.

## Failure semantics

MySQL DDL performs implicit commits. Splitting migrations limits the failure boundary but does not make a multi-table migration atomic. If an unrecorded migration fails after creating a table, do not rerun against that partial schema and do not edit the migration ledger. For a disposable development installation, drop and recreate the positively identified database and rerun the documented sequence.

## Phase 2 WP3

WP3 introduces no migration or raw SQL artifact. Its scoped Audit queries use the existing v1.1 `audit_events` indexes; the register therefore remains unchanged at order 7. Any future index change requires observed MySQL query-plan evidence, a paired migration/raw SQL artifact, and separate approval.

## Phase 3 WP1

| Order | Migration | Raw SQL | Purpose |
|---:|---|---|---|
| 8 | `2026-10-06-000000 CreatePhaseThreeProducts` | `docs/database/sql/phase-03/20261006_000000_create_phase_three_products.sql` | Tenant Product master; duplicate normalized names remain legal |
| 9 | `2026-10-06-000100 CreatePhaseThreeStockPositions` | `docs/database/sql/phase-03/20261006_000100_create_phase_three_stock_positions.sql` | Protected Shop/Product zero projection and threshold |

Apply after Phase 2. Roll back Stock Positions before Products. `last_movement_id` intentionally has no FK until WP2 creates `stock_movements`.

## Phase 3 WP2

| Order | Migration | Raw SQL | Purpose |
|---:|---|---|---|
| 10 | `2026-10-06-000200 CreatePhaseThreeStockIntakes` | `phase-03/20261006_000200_create_phase_three_stock_intakes.sql` | Draft/posted Intake header |
| 11 | `2026-10-06-000300 CreatePhaseThreeStockIntakeItems` | `phase-03/20261006_000300_create_phase_three_stock_intake_items.sql` | Positive acquisition lines and cost snapshots |
| 12 | `2026-10-06-000400 CreatePhaseThreeStockMovements` | `phase-03/20261006_000400_create_phase_three_stock_movements.sql` | Immutable movement ledger |
| 13 | `2026-10-06-000500 AddPhaseThreePositionMovementForeignKey` | `phase-03/20261006_000500_add_phase_three_position_movement_fk.sql` | Scoped circular last-movement pointer |

## Phase 3 WP4 closure

No migration is owned by WP4. Static review retains the six Phase 3 migration/raw-SQL pairs. Phase 4 owns the deferred scoped Sale Item FK only after `sale_items` exists; Phase 7 owns the deferred reconciliation variance FK.


## Phase 4 WP1

Two ordered App migrations: `2026-11-02-100000_CreatePhaseFourReconciliationSessions` and `2026-11-02-100100_CreatePhaseFourReconciliationFinancialCounts`. Full v1.1 session/count definitions; existing/self scope FKs only, no future Device/Sales dependencies. Paired SQL: `docs/database/sql/phase-04/20261102100000_reconciliation_sessions.sql` and `20261102100100_reconciliation_financial_counts.sql`. Existing Phase 3 evidence is untouched. Development rollback reverses counts then sessions; verify batches before rollback.

## Phase 4 WP2

Seven ordered App migrations after WP1: `2026-11-03-100000` Customers, `100100` Sales, `100200` Sale Items, `100300` Sale Payments, `100400` Receivables, `100500` Receivable Adjustments, `100600` deferred scoped `stock_movements.sale_item_id` FK. Each has paired SQL under `phase-04/20261103*.sql`; see the [WP2 delivery register](../Phase-4/Phase_4_Work_Package_2_Delivery_Report.md) for exact filenames/apply/rollback instructions. Six full v1.1 commercial tables; only Customer and DRAFT workflows exposed. Reverse dependency order starts by dropping the late FK. Inspect batch boundaries; MySQL DDL implicitly commits. Native MySQL apply/rollback verification remains an owner development-checkout task.

## Phase 4 WP3

No migration or raw SQL is introduced. WP3 uses the six accepted commercial tables and deferred Sale Item FK from WP2. It replaces draft-only cost placeholders at the atomic DRAFT→POSTED transition. Posted Sales/payments/Receivables/stock evidence must be preserved; downgrading code is not permission to drop schema/history. Inspect migration status before deployment and do not run WP2 down migrations around posted references. See [WP3 delivery](../Phase-4/Phase_4_Work_Package_3_Delivery_Report.md).

## Phase 4 WP4 readiness — pending decision

No migration is applied or introduced by the readiness change. The [proposed Return/compensation contract](../Phase-4/Phase_4_WP4_Proposed_Return_and_Compensation_Contract.md) contains review-only SQL for an explicit `ck_sales_composition` amendment. It must not be copied into the database before the owner decision and a new paired forward migration/preflight/rollback guard. Existing WP2 migration artifacts remain unchanged.


## Phase 4 WP4 — approved compensation composition amendment

Owner approved the debt-first Return/refund, historical-cost provenance, linked compensation and dependency-protected reversal/replacement contract on 2026-11-05. WP4-A adds migration 2026-11-05-100000 AmendPhaseFourSaleCompensationComposition with paired SQL phase-04/20261105100000_amend_sale_compensation_composition.sql. It preflights unexpected ordinary SALE rows referencing original_sale_id, changes only ck_sales_composition, and refuses rollback while posted SALE-shaped compensation rows exist. No business evidence is rewritten. Native MySQL 8.4 execution is still required; do not apply on production based solely on static review. Return writers, reversal/replacement, UI/evidence and integrated acceptance remain outstanding.


## WP4-D — 2026-11-06

D introduces no migration or table/column. Apply A 2026-11-05-100000 after accepted WP2 ordering through Spark once; preserve its preflight and used-compensation downgrade refusal. No old applied migration/paired SQL is rewritten. Native fresh/upgrade/repeat/rollback verification remains in the final checklist.


## Phase 5 WP1 — debt foundation

Apply after all accepted Phase 4 migrations. Two new ordered migrations only:

| Order | Migration | Equivalent MySQL SQL | Purpose |
|---:|---|---|---|
| 24 | `2026-11-11-190000_CreatePhaseFiveDebtPayments` | `phase-05/20261111190000_debt_payments.sql` | Immutable scoped Cash/Transfer collection parent and linked inverse identity |
| 25 | `2026-11-11-190100_CreatePhaseFiveDebtPaymentAllocations` | `phase-05/20261111190100_debt_payment_allocations.sql` | Positive allocation evidence; scoped payment/Receivable FKs and unique payment/bill |

No previous migration/table is edited or recreated. Existing `receivable_adjustments` is reused. Shield actor FK remains `INT UNSIGNED`; domain IDs are `BIGINT UNSIGNED`. No aggregate CHECK is claimed: allocation sums, same-Customer attribution and matching compensations are owning-service invariants. WP1 exposes no collection writer.

Native MySQL apply/constraint verification remains an Owner development gate. DDL implicitly commits; identify/back up the database before applying. Do not execute raw SQL in addition to Spark migrations. If unrecorded DDL leaves a partial table, inspect/recover a disposable copy instead of forging migration history. Rollback only empty new-table development copies in child→parent order; never delete accepted financial evidence to undo deployment. See [WP1 setup/manual guide](../manual-verification-phase-05-wp1.md) and [contracts](../Phase-5/Phase_5_WP1_Integration_Contracts.md).


## Phase 5 WP2 — no new migration

Collection uses the accepted WP1 debt_payments/debt_payment_allocations tables and existing Receivables/Audit/sync_changes. Reason/review/replay evidence uses bounded immutable Audit metadata, without a command-hash/status/wallet table or receipt snapshot column. No existing migration/raw SQL is rewritten. Equivalent retries do not change accepted rows. Do not drop payment tables to roll back code after collections; see [collection operations](../operations/phase-05-debt-collection.md).

## Phase 5 WP3 — no migration

Reuses accepted WP1 debt_payments/debt_payment_allocations and existing receivable_adjustments unchanged. Service-level exact inverse/same-Customer/sum/no-chain/refund-dependency checks complement existing unique links/scoped FKs; no cross-row CHECK claim. Optional operation UUID argument to the adjustment participant changes no column. Audit/causal correlation identifies atomic pair without a replacement/status/balance column. Existing paired raw SQL is unchanged; destructive rollback of accepted evidence remains prohibited. Runtime needs SELECT/INSERT only for immutable correction evidence plus existing Audit/change rights.

## Phase 5 WP4 — no migration

Statements derive from existing Receivables, signed adjustments, payment headers/allocations, Sales and active generation/scoped changes in a read-only snapshot. No statement/cursor table, materialized balance/status, modified FK or new permission seed. Existing paired WP1 SQL unchanged. Page tokens carry sealed state rather than Phase10 cursor storage. Runtime reads need existing SELECT rights; deployment encryption.key remains private configuration outside the inventory.

## Phase 6 WP1 — 13 November 2026

Phase 6 WP1 adds `2026-11-13-100000_CreatePhaseSixFinancialMovements.php` after accepted Phase 5 migrations; raw twin [20261113100000_financial_movements.sql](sql/phase-06/20261113100000_financial_movements.sql). Creates only financial_movements with 16 columns and scoped self FKs; created_by_user_id matches Shield INT UNSIGNED. Existing migrations remain unchanged. Apply PHP migration or reviewed raw SQL to separate native fixtures, never both to one existing table. Down refuses nonempty evidence. Native empty/populated migration and constraint acceptance remain pending P6-01/P6-02. See [operations](../operations/phase-06-wp1-release.md).
