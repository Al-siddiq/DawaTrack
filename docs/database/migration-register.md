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
