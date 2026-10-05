# Phase 2 WP1 — Typed Audit integrity and protected-command contract

## Delivered boundary

WP1 hardens the existing Phase 1 Audit writer. All existing sensitive write paths use immutable `AuditAppendCommand` objects, explicit authority types, approved vocabulary, centralized reason/metadata/causal policies, and one canonical correlation UUID. It adds no schema, Audit query API/UI, or change sequence.

## Audit authority

Tenant-user commands carry the authenticated user, active membership, Tenant, and Shop already resolved by the owning service. Platform commands carry a verified Platform Administrator and optional affected target scope without a Tenant membership. Identity security commands are unscoped and self-attributed. Scheduled domain verification uses system authority.

The Audit service revalidates user/membership/scope relationships inside the owning transaction. Failure is non-enumerating and aborts that transaction. `actor_device_id` is always `NULL`.

## Protected-record lifecycle

Records belong to one of three categories:

1. **Draft/uncommitted:** may be explicitly edited or discarded before becoming authoritative.
2. **Mutable master/configuration:** changes only through its owning service; important changes record minimized before/after Audit.
3. **Posted/finalized evidence:** has no generic update/delete route or repository method.

A future correction to protected evidence must:

- use an explicit domain action such as reverse or compensate;
- lock the original deterministically;
- prove the original is eligible and has not already received the prohibited duplicate correction;
- retain the original;
- create the domain-specific compensating record and source/reversal FK;
- require the designated permission and nonblank reason;
- append causal Audit inside the same transaction;
- optionally create a separately validated replacement.

An Audit causal ID never replaces the domain FK or calculation invariant. WP1 creates no speculative correction table or endpoint.

## Duplicate safety before Phase 10

Every command uses its domain mechanism:

| Command class | Protection | Repeat outcome |
|---|---|---|
| Resource creation | permanent UUID and scoped/global unique constraints | resource-specific `409` conflict |
| Permission grant/revoke | membership advisory lock plus active-grant state | `PERMISSION_UNCHANGED` |
| Staff activation/deactivation | membership serialization plus conditional status/cap checks | `STAFF_STATUS_UNCHANGED`, `STAFF_CAP_REACHED`, or membership conflict |
| Shop/Tenant lifecycle | row lock, allowed transition table, expected-state update | established `CONFLICT` |
| Subscription operation | Tenant/advisory locks, half-open periods, overlap/cap checks | `SUBSCRIPTION_OVERLAP` or `SUBSCRIPTION_STAFF_CAP_CONFLICT` |
| Domain lifecycle | unique hostname and conditional lifecycle checks | `DOMAIN_STATE_CONFLICT` |
| Future reversal | original-row lock and unique domain reversal/source link | domain-specific reversal conflict |

The UI pending state reduces accidental repeated submission but never replaces server invariants. There is no generic idempotency table, `idempotency_key`, `DUPLICATE_OPERATION`, durable replay, or `sync_receipts`.

## Existing Audit paths migrated

- Tenant onboarding and initial Owner;
- Staff create/status/credential reset;
- permission grant/revoke;
- Shop settings;
- branding profile and asset replacement;
- Owner and scheduled domain lifecycle;
- subscription creation/change and scheduled expiry evidence;
- Tenant/Shop lifecycle;
- forced password change;
- Platform MFA establishment/clearing.

All remain in their owning transaction except the documented pre-schema MFA bootstrap guard.

## Frontend reason interaction

The Shop Staff/permission/credential reason dialog uses the shared `useReasonDialog` module. It trims and bounds reasons, prevents repeat submission per dialog, retains the reason after recoverable failure, restores initiating focus, traps Tab focus, supports Escape when not pending, exposes live validation, and supplies explicit labels and busy text. No Audit page, correction button, Device, or sync UI is present.
