# Migration and SQL register

| Phase | CodeIgniter migration | Equivalent MySQL 8.4 SQL | Status |
|---|---|---|---|
| 0 | None | None | Foundation only |
| 1 | `2026-09-25-190000_CreatePhaseOneFoundation.php` | `phase-01/001_phase_01_foundation.sql` | Tenant, Shop, Identity, Branding, Domain, Subscription, Audit and Sync Generation foundation |

Shield's pinned vendor migrations run first and remain Shield-owned. The Phase 1 migration executes the registered SQL verbatim so both paths have identical constraints, indexes, checks, generated guards and FKs. `audit_events.actor_device_id` receives its approved late FK in Phase 7 after `registered_devices` exists.

Before formal Phase 1 production approval, the registered SQL was corrected in place to the approved `utf8mb4_unicode_ci` ordinary-text default. No durable applied DawaTrack database was present in the implementation environment, so no historical corrective migration was introduced. Explicit `ascii_bin` UUID and normalized-hostname columns remain intentional.

## WP4 schema assessment

WP4 required no migration. Existing immutable `subscriptions`, `subscription_events`, Tenant/Shop lifecycle columns, plan snapshots, Audit table, indexes, foreign keys, checks, `utf8mb4_unicode_ci` defaults, and intentional `ascii_bin` identifiers support the completed operations. No mutable current-subscription status or lifecycle convenience table was added.
