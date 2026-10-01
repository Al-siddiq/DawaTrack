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

The former monolithic `CreatePhaseOneFoundation` migration and consolidated executable SQL were removed before approval because retaining them would create a duplicate DDL path.

`audit_events.actor_device_id` remains nullable without an FK in Phase 1. Phase 7 adds `fk_audit_events_device` only after `registered_devices` exists.

## Failure semantics

MySQL DDL performs implicit commits. Splitting migrations limits the failure boundary but does not make a multi-table migration atomic. If an unrecorded migration fails after creating a table, do not rerun against that partial schema and do not edit the migration ledger. For a disposable development installation, drop and recreate the positively identified database and rerun the documented sequence.
