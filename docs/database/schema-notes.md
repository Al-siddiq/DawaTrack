# Schema implementation notes

Database / Schema Design v1.1 remains authoritative. Phase 1 implements the approved tables without adding deferred business structures. Shield owns its identity tables and migrations. All DawaTrack UUID and normalized-hostname columns use intentional ASCII binary comparison, business tables use InnoDB/`utf8mb4_unicode_ci`, and timestamps are UTC `DATETIME(6)`.

The one-Shop Phase 1 invariant is serialized in onboarding rather than enforced with `UNIQUE(tenant_id)`, preserving the approved future one-to-many shape. `audit_events.actor_device_id` is present and nullable; its Device FK is intentionally deferred to Phase 7 exactly as the roadmap requires.

## WP4 entitlement and lifecycle use

Current entitlement is derived from the immutable half-open predicate `starts_at <= UTC now AND ends_at > UTC now`. Application advisory locks serialize Tenant subscription creation and prevent overlapping periods before inserting the period, event, and Audit in one transaction. Existing Tenant and Shop status/timestamp columns support suspension, terminal closure, deactivation and reactivation without deletion. Expiry events are optional repeat-safe evidence and do not determine access.
