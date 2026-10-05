# Schema implementation notes

Database / Schema Design v1.1 remains authoritative. Phase 1 consists of six dependency-ordered application migrations and six equivalent MySQL 8.4 SQL files. Shield owns identity tables; its migrations must run before DawaTrack. The pinned Shield 1.4.1 `users.id` contract is `INT UNSIGNED NOT NULL PRIMARY KEY` on InnoDB, so only DawaTrack columns referencing Shield users use `INT UNSIGNED`. DawaTrack-owned IDs remain `BIGINT UNSIGNED`.

All business tables use InnoDB and `utf8mb4_unicode_ci`. UUIDs, normalized hostnames, correlation identifiers, and branding hexadecimal colours use intentional ASCII binary comparison. Timestamps are UTC `DATETIME(6)`.

The one-Shop Phase 1 invariant remains serialized in onboarding rather than enforced by `UNIQUE(tenant_id)`. `audit_events.actor_device_id` is present and nullable; its FK is intentionally deferred to Phase 7.

Phase 2 WP1 required no DDL. WP2 adds only the approved `sync_changes` notification/index table after its Generation, Tenant and Shop parents. It stores resource identity and ordering, never business payload. Application code continues to insert `NULL` for `audit_events.actor_device_id`; Device attribution remains deferred to Phase 7.

Each migration executes complete SQL strings directly. No generic semicolon parser is used. Normal migrations deliberately omit `IF NOT EXISTS`: physical schema drift must fail loudly instead of being hidden.

MySQL DDL is implicitly committed. A failed, unrecorded migration can therefore leave its earlier statements behind. Recovery for a disposable database is to verify the target, drop/recreate it, and rerun from an empty baseline—not to forge migration-history rows.
