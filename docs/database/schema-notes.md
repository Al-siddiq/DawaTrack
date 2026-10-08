# Schema implementation notes

Database / Schema Design v1.1 remains authoritative. Phase 1 consists of six dependency-ordered application migrations and six equivalent MySQL 8.4 SQL files. Shield owns identity tables; its migrations must run before DawaTrack. The pinned Shield 1.4.1 `users.id` contract is `INT UNSIGNED NOT NULL PRIMARY KEY` on InnoDB, so only DawaTrack columns referencing Shield users use `INT UNSIGNED`. DawaTrack-owned IDs remain `BIGINT UNSIGNED`.

All business tables use InnoDB and `utf8mb4_unicode_ci`. UUIDs, normalized hostnames, correlation identifiers, and branding hexadecimal colours use intentional ASCII binary comparison. Timestamps are UTC `DATETIME(6)`.

The one-Shop Phase 1 invariant remains serialized in onboarding rather than enforced by `UNIQUE(tenant_id)`. `audit_events.actor_device_id` is present and nullable; its FK is intentionally deferred to Phase 7.

Phase 2 WP1 required no DDL. WP2 adds only the approved `sync_changes` notification/index table after its Generation, Tenant and Shop parents. It stores resource identity and ordering, never business payload. Application code continues to insert `NULL` for `audit_events.actor_device_id`; Device attribution remains deferred to Phase 7.

Each migration executes complete SQL strings directly. No generic semicolon parser is used. Normal migrations deliberately omit `IF NOT EXISTS`: physical schema drift must fail loudly instead of being hidden.

MySQL DDL is implicitly committed. A failed, unrecorded migration can therefore leave its earlier statements behind. Recovery for a disposable database is to verify the target, drop/recreate it, and rerun from an empty baseline—not to forge migration-history rows.

## Phase 2 WP3 no-DDL decision

The implemented Owner, Platform, and support Audit queries use the v1.1 `audit_events` scope/time, resource, actor, correlation, and category indexes. WP3 adds no migration, table, column, copy, support-session record, or saved-filter structure. Runtime MySQL query-plan review remains blocked with the carried database verification debt; an index was not added without that evidence.

## Phase 3 WP1

WP1 adds only `products` and `stock_positions`. Product creation atomically materializes the current Shop's zero `UNESTABLISHED` position. Stock quantity/cost/pointer have no generic mutation boundary; threshold is independently mutable and versioned. `stock_positions.last_movement_id` is nullable without an FK until the WP2 parent exists. Stock-position change notifications use the Product public UUID as the logical Shop-scoped position identity because frozen v1.1 gives `stock_positions` no separate public UUID.

## Phase 3 WP2

WP2 adds `stock_intakes`, `stock_intake_items`, `stock_movements`, then the scoped position-pointer FK. Duplicate Product lines are legal. Draft rows are mutable/discardable; posted evidence has no update/delete boundary. `sale_item_id` and `reconciliation_variance_id` exist and are indexed but their scoped FKs are deliberately owned by Phase 4 and Phase 7. Only `INTAKE_ITEM` sources and `OPENING`/`STOCK_INTAKE` commands are exposed now.
