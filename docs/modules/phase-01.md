# Phase 1 modules

## Identity
Shield owns authentication tables, password recovery, session rotation and platform groups. DawaTrack owns durable Tenant memberships and explicit, versioned Staff grants. Staff authorization is deny-by-default; Primary Owners receive normal Tenant authority by role. Users and membership evidence are deactivated, never deleted.

## Tenant and Shop
Onboarding is serialized and atomic: Tenant, exactly one Shop, default branding, platform hostname, active Primary Owner, Trial subscription/event and Audit evidence commit together. The one-Shop rule is service-enforced without a `UNIQUE(tenant_id)` redesign.

## Subscription
Plans are controlled bootstrap data. Subscription rows are immutable half-open entitlement periods; status is derived from server UTC time. Effective access combines Tenant, Shop and entitlement state into `FULL`, `READ_ONLY`, `BLOCKED` or `CLOSED`. Staff limits use the current entitlement snapshot.

## Branding and domains
Brand profiles are versioned mutable configuration. Asset replacement validates decoded bytes, MIME, size and icon dimensions, uses opaque storage names outside the public tree, and atomically deactivates old metadata. Custom hostnames are normalized and globally unique; knowledge of a hostname never grants authority. Activation requires verified ownership and active TLS.

## Audit
Every sensitive service writes immutable Audit evidence in the same transaction as the mutation. Application database credentials should receive SELECT/INSERT but no UPDATE/DELETE on `audit_events`. `actor_device_id` intentionally has no FK until Phase 7 creates `registered_devices`.
