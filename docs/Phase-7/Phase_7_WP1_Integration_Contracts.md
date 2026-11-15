# Phase 7 WP1 — Device and control integration contracts

15 November 2026. Implements approved WP1 only. Standard controls, cutoff capture, readiness challenges/acknowledgement rows, count/variance tables and transport are reserved for WP2/WP3.

## Persistence / setup

Two forward migrations: 2026-11-15-100000 CreatePhaseSevenRegisteredDevices, then 100100 AddPhaseSevenAuditDeviceForeignKey. SQL twins are under docs/database/sql/phase-07. Device columns follow schema v1.1 §16.1; user keys match Shield INT UNSIGNED. No prior migration is rewritten. Existing Audit actor_device_id gets its deferred parent FK with orphan/cross-scope preflight; application validation also requires ACTIVE same-Tenant/Shop Device context. Runtime setup fails closed if Device table/required columns/Audit FK are missing. Down refuses evidence-bearing Device/Audit state; never drop business rows for rollback.

## Device identity and credentials

Cookie-proof and authenticated active membership are independent. Owner-only administration is rechecked under Tenant → Shop → active membership → Device locks using CommercialAccess, current FULL entitlement and current PRIMARY_OWNER role. Staff cannot list/admin other Devices; active Staff may read and explicitly validate only the current cookie-proven Device. READ_ONLY may read; validation is a timestamp/Audit mutation and requires FULL. BLOCKED/CLOSED deny.

Enrollment receives only lowercase UUIDv4 public_id/device_uuid and display_name (1–100 characters). Names reject control characters. Tenant/Shop/actor/status/verifier/offline fields cannot be posted. Permanent marker IDs are nonsecret; neither one is Device proof. Enrollment issues 32 random bytes as a URL-safe secret; DB stores a SHA-256 verifier only. Production cookie is __Host-dt_device, host-only Path=/, Secure, HttpOnly, SameSite=Strict. Non-production uses dt_device; Secure is disabled only for non-HTTPS development. Cookies are not returned in JSON. No secrets, bodies or verifier in Audit/log/browser script storage. Browser cookie lifetime is not an offline authorization lease.

Owner enrolls each browser/origin explicitly. An existing equivalent enrollment recovers only with matching current ACTIVE cookie proof; changed/foreign UUID or absent proof conflicts. Unknown registration is resolved via current status/Owner history, not a new UUID. Explicit Owner recovery requires the same public/device UUID and mandatory reason; it rotates proof, never rebinds Tenant/Shop or reactivates revoked state. Another installation's old proof ceases working. Lost recovery replies can require another explicit audited rotation; no automatic retry. A revoked installation remains historical; new enrollment is explicitly offered with new IDs, never silently substituted for an unresolved request.

last_seen_at records a successful online enrollment/recovery/check; last_validated_at is null on issue/rotation until current/validate proves the issued cookie roundtrip. offline_trust_expires_at and sync timestamps remain null. No PIN, provision, offline lease, cursor, Sync Receipt, outbox or acknowledgement is claimed. Revocation remains visible to a matching cookie but cannot validate or be reactivated. A Device cookie never restores membership/permission/session after sign-out.

## API

All routes are authenticated current-Shop /api/v1/shop, session-CSRF protected, private no-store, standard envelopes/correlation. POSTs share a 20/minute identity bucket through existing sensitive throttling.

| Method/path | Authority | Closed input / result |
|---|---|---|
| GET /devices?page=&per_page= | Current Owner; authorized READ_ONLY read | Safe devices, meta.pagination; per_page≤50; stable registered_at,id order |
| GET /devices/current | Active Shop member | Safe cookie-proven Device including revoked status, or null; no mutation |
| POST /devices | Current Owner + FULL | public_id,device_uuid,display_name; safe Device and HttpOnly Set-Cookie |
| POST /devices/current/validate | Active member + FULL + ACTIVE matching proof | Empty JSON object; safe Device with validated time |
| POST /devices/{uuid}/rename | Current Owner + FULL | display_name; equivalent name is harmless repeat |
| POST /devices/{uuid}/revoke | Current Owner + FULL | reason; equivalent repeated reason recovers original decision; changed reason conflicts |
| POST /devices/{uuid}/recover | Current Owner + FULL | device_uuid,reason; explicit verifier rotation and HttpOnly Set-Cookie |

Device DTO allowlist: public_id/display_name/status/registered_at/last_seen_at/last_validated_at/revoked_at/revocation_reason/updated_at. Never internal Device/user/membership IDs, device_uuid, verifier, credentials or raw Audit. Resource UUID alone cannot authorize own-Device access. Device errors include DEVICE_SETUP_REQUIRED, DEVICE_IDENTITY_CONFLICT, DEVICE_NOT_FOUND, DEVICE_NOT_ACTIVE, DEVICE_RECOVERY_CONFLICT and DEVICE_PROOF_REQUIRED.

## Audit and publication

Actions: device.registered, device.renamed, device.revoked, device.credential_recovered, device.validated; category SYNC, resource registered_device. Required human reason for revoke/recover; explicit action descriptions for enrollment/rename/online-check. Issuance/admin decisions use the authenticated user actor; actual current-cookie validation uses tenantDevice after proof verification. AuditAuthorityValidator independently rejects inactive/foreign Device actors. Existing callers retain tenantUser with null Device; no arbitrary browser Device attribution is introduced into older business services.

All domain effects and required Audit commit with one final registered_device UPSERT. No further domain locks follow publication. Equivalent no-op enroll/name/revoke retries emit neither duplicate Audit nor changes. Explicit online checks update real timestamps and append evidence; they are user actions, not a background heartbeat loop. No generic Device pull API or sync transport.

## Shared control boundary

ControlScopeGuard locks Tenant → Shop → current membership, checks current FULL access and requested grants, then controls/source/Product/position locks in deterministic order. Caller owns commit; guards never start/nest/commit. StockControlGuard excludes active STOCK/FULL; existing CashControlGuard preserves finalized OPENING requirement and excludes active CASH/FULL. Relevant statuses: COUNTING,COUNTS_COMMITTED,REVIEW. FULL overlaps both; CASH/STOCK remain independent. Pure Transfer Financial Movement is not Cash; every posted Sale/Return/compensation is Stock-affecting even with zero Cash/full credit.

Integrated Stock roots/guard before first source lock: Intake posting/replacement posting; manual movement/Correction; manual reversal; Intake reversal; Sale/replacement posting (after saved replay), Return posting (after saved replay), commercial compensation execution. Existing CommercialAccess already owns roots and current permission rechecks. Product create/edit/deactivate now also serializes roots and rechecks product.manage for coherent frozen-dataset boundaries, without blocking zero-quantity master changes as Stock effects. Draft/threshold operations have no posted quantity/cost effect and retain their own existing behavior. A future adjustment must use the same root/scope contract; no adjustment endpoint exists in WP1.

## Blind source-read foundation

Authenticated Shop source routes call BlindReadPolicy across sessions. For standard COUNTING, a current Primary Owner/count-granted actor or starter cannot access scope-revealing source routes. STOCK covers products, Stock Intakes/movements, Sales/drafts/lookups, Returns/compensations; CASH covers financial/opening/Sales/Return/Debt/Customer evidence; Audit is restricted for either. Check applies to revealing preview/mutation responses as well as GET. The policy returns BLIND_COUNT_ACTIVE with no expected data. Other roles retain independent read policy. OPENING confirmation is unchanged; standard workflows are not exposed in WP1. Already remembered data cannot be erased.

WP2 must deliver separate blind DTOs, count/review permission policies and blind-safe outcome-recovery responses. Until such a response exists, an otherwise revealing source status route is deliberately denied during blind counting, not exempted as a privacy bypass. Existing source recovery remains unchanged outside that future standard COUNTING interval. The filter is an API boundary; internal future reports/diagnostics must call the same policy/approved projection rather than expose another source route.

## Cutoff and readiness handoff to WP2

WP1 supplies locks, Device trust and scoped source-read restriction, not a fake readiness endpoint. WP2 must lock all roots/domain rows before generation, choose fresh current snapshots (DebtReadSnapshot participates in caller transaction), server UTC cutoff and authoritative locking-read watermark, append Audit and publish generation-last. Preserve independent predecessors, count-time fixed dataset, exact posted_at interval and no mutable finalized evidence.

Relevant set contract accepted with the plan: all ACTIVE same-Shop Devices at start, no last-seen timeout; typed per-Device start Audit associations plus count/digest summary within metadata bounds, no new table or pending ACK. Actual ack/exclusion insert-once decisions arrive in WP2's approved table. Unresolved revoked Device needs explicit Owner exclusion; existing validated ACK remains historical. New Devices never rewrite an existing set. Readiness proof is an integrity-protected short-lived target-bound online challenge, not an unimplemented pull cursor. Generation change prevents completion and requires safe abort/new attempt. No standard sessions are created here.

## Browser integration and recovery

DevicesPanel is in the existing Shop Menu/sidebar; Staff see only own-installation status/check. Owner enrollment/list/name/revoke/recover uses shared fields/buttons/dialog/status/navigation guards. Fixed page size20, server cap50, no load-all. Current status is cleared on failed/offline refresh. Nonsecret public/device UUID marker is stored only in a host/user/Shop recovery namespace; no amount/reason/body/credential/queue. Storage failure blocks enrollment and explains recovery instead of clearing storage. Unknown network/5xx result blocks another command until explicit status refresh. No automatic POST, reconnect repost or background job. ReadOnly/denied/offline/setup/loading/empty/pending/error states are explicit. Native layout/keyboard/cookie/CSRF remain manual acceptance.

## Inherited Phase 6 repair

Native execution remains unclaimed. The PHP grammar check found FinancialMovementCashComponentQuery.php already corrupt at accepted WP4/main: a truncated Audit-fingerprint REGEXP in its SQL and a repeated 164-line tail after the completed class. WP1 restores the correlated count predicate and removes the duplicate tail, retaining the first class's stricter cross-channel source/reversal checks. This is a separately recorded baseline blocker fix, not a new expected-Cash feature. Prior manifests remain historical; the WP1 manifest identifies the reviewed new bytes. Native MySQL/F1/F3 verification is required before release.
