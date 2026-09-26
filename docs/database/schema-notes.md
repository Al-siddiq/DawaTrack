# Schema implementation notes

Database / Schema Design v1.1 remains authoritative. Phase 1 implements the approved tables without adding deferred business structures. Shield owns its identity tables and migrations. All DawaTrack UUID columns use ASCII binary comparison, business tables use InnoDB/`utf8mb4_0900_ai_ci`, and timestamps are UTC `DATETIME(6)`.

The one-Shop Phase 1 invariant is serialized in onboarding rather than enforced with `UNIQUE(tenant_id)`, preserving the approved future one-to-many shape. `audit_events.actor_device_id` is present and nullable; its Device FK is intentionally deferred to Phase 7 exactly as the roadmap requires.
