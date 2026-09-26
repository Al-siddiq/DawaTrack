# Phase 1 completion surfaces

The Shop shell exposes only implemented Phase 1 account surfaces: Staff, Shop settings, branding/assets, domains and subscription/access. All API context is server-derived from the authenticated Shield user plus an active verified hostname mapping; client Tenant IDs are never accepted as authority. Owner mutations require Primary Owner role, current scoped membership and effective access.

The Platform Admin onboarding UI uses an explicit review step and invokes the atomic onboarding service. Shield owns login, recovery, credentials, sessions, groups and MFA. Branding uses constrained colour variables only. Assets are decoded and stored outside `public`; authenticated delivery verifies Tenant and Shop scope. Custom-domain DNS and TLS are lifecycle states, never authority.
