# Phase 2 WP2 — commit-ordered server change sequence

WP2 adds `sync_changes` and the internal `ChangeSequenceService`. Publication requires an existing owner transaction on the same connection. Commands validate Tenant/optional Shop scope, allow-listed logical resource type, permanent UUID, change kind, optional operation UUID, and reuse the WP1 correlation.

## Evidence classification

| Operation | Audit | Change resource(s) |
|---|---|---|
| Onboarding | yes | Tenant, Shop, Owner membership, branding profile, platform domain, subscription |
| Staff create/status | yes | membership (`DEACTIVATE` when deactivated) |
| Permission grant/revoke | yes | membership authorization projection |
| Shop settings | yes | Shop |
| Branding profile/active asset | yes | branding profile/new active asset |
| Domain request/rotation/verification/lifecycle | yes | domain (`DEACTIVATE` only for deactivation) |
| Subscription create/change/expiry evidence | yes | Tenant-wide subscription |
| Tenant/Shop lifecycle | yes | Tenant/Shop; inactive/closed/suspended use `DEACTIVATE` |
| Credential reset/password change/Platform MFA | yes | none |
| Reads, health, pre-transaction validation, rejected no-op | no | none |

All publishers append Audit first, then acquire the generation serialization lock and append changes last. No domain lock is acquired afterwards. Existing domain-specific conflicts remain authoritative; no generic replay or duplicate-operation code exists.
