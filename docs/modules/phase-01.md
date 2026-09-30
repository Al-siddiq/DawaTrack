# Phase 1 modules

## Identity
Shield owns authentication tables, password recovery, password hashing, session rotation and platform groups. DawaTrack owns durable Tenant memberships, cross-session revocation timestamps and explicit, versioned Staff grants. Staff authorization is deny-by-default; Primary Owners receive normal Tenant authority by role. Staff deactivation and credential reset revoke remembered and already-authenticated Shop access. Users and membership evidence are deactivated, never deleted.

## Tenant and Shop
Onboarding is globally serialized and atomic on the shared default database connection: the Shield Owner identity, Tenant, exactly one Shop, default branding, platform hostname, active Primary Owner membership, Trial subscription/event and Owner/onboarding Audit evidence commit together. Lock acquisition, active Sync Generation, Trial plan, identity and hostname availability are verified before creation. The one-Shop rule is service-enforced without a `UNIQUE(tenant_id)` redesign.

Staff create/reactivate operations use a shared membership advisory lock, enforce the current entitlement snapshot cap and prevent one identity from holding simultaneous active ordinary memberships across Tenants. Permission mutations use a per-membership lock; duplicate grant/revoke requests return conflict without false Audit evidence.

## Shop access and settings

The Shop context response supplies safe role, access mode, public Shop/Tenant presentation identifiers and effective permission/capability data. The shell loads Owner-only resources only for Owners; Staff receive a stable common shell with their effective grants. Owner settings accept only the approved mutable profile fields and validate normalized phone/email, lengths and IANA timezone values. Audit stores bounded actual before/after changes.

## Subscription
Plans are controlled bootstrap data. Subscription rows are immutable half-open entitlement periods; status is derived from server UTC time. Effective access combines Tenant, Shop and entitlement state into `FULL`, `READ_ONLY`, `BLOCKED` or `CLOSED`. Staff limits use the current entitlement snapshot.

## Branding and domains
Brand profiles are versioned mutable configuration. Asset replacement validates decoded bytes, MIME, size and icon dimensions, uses opaque storage names outside the public tree, and atomically deactivates old metadata. Custom hostnames are normalized and globally unique; knowledge of a hostname never grants authority. Activation requires verified ownership and active TLS.

## Audit
Every sensitive service writes immutable Audit evidence in the same transaction as the mutation. Application database credentials should receive SELECT/INSERT but no UPDATE/DELETE on `audit_events`. `actor_device_id` intentionally has no FK until Phase 7 creates `registered_devices`.
