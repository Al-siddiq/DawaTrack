# Phase 1 completion surfaces

The Shop shell exposes only implemented Phase 1 account surfaces: Staff, Shop settings, branding/assets, domains and subscription/access. All API context is server-derived from the authenticated Shield user plus an active verified hostname mapping; client Tenant IDs are never accepted as authority. Owner mutations require Primary Owner role, current scoped membership and effective access.

The Platform Admin onboarding UI uses an explicit review step and invokes the atomic onboarding service. Shield owns login, recovery, credentials, sessions, groups and MFA. Branding uses constrained colour variables only. Assets are decoded and stored outside `public`; authenticated delivery verifies Tenant and Shop scope. Custom-domain DNS and TLS are lifecycle states, never authority.

WP2 completes the onboarding/identity slice: Owner identity creation participates in the same transaction as the Tenant graph and Audit; active Sync Generation and lock acquisition are mandatory. Staff create/reactivate is serialized against plan caps, deactivation/reset revokes active access, and permission state/history is server-authoritative. The Shop shell is role-aware, so Staff do not bootstrap Owner-only APIs. Shop profile edits are normalized, field-validated and audited with bounded before/after values. Phase 1 remains unapproved until WP3 and WP4 are delivered and all runtime verification debt is executed.

## WP4 completion boundary

Phase 1 now includes the focused `SubscriptionService`/`SubscriptionQuery`, Platform Tenant/Shop lifecycle service, current/history/future Owner presentation, and recent-MFA Platform operations UI. Entitlements are immutable half-open periods with serialized no-overlap creation, snapshot limits, events, and Audit. Tenant/Shop status dominates entitlement through `AccessGate`. No Phase 2 module or change-sequence work is part of this boundary.
