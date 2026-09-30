# Phase 1 Recovery & Completion — Work Package 4 Delivery Report

## A. Work Package Delivered

**WP4 — Subscription Lifecycle, Tenant Operations, Access Closure, Audit Closure & Final Phase 1 Acceptance**

## B. Implementation Summary

WP4 supplies immutable subscription commands and query classification, serialized period/event/Audit creation, Trial extension, manually confirmed Starter/Growth operations, expiry evidence, Tenant and Shop lifecycle operations, final access-mode integration, centralized Audit metadata safety, responsive Platform Admin operations, an authoritative Owner subscription view, and reconciled Phase 1 operating/acceptance documentation. Phase 2 was not started.

## C. WP1–WP3 Foundations Preserved

Shield, disabled registration, Platform Admin Email2FA/recent MFA, CSRF, throttling, sessions, response envelopes, `utf8mb4_unicode_ci`, intentional `ascii_bin`, atomic onboarding, Tenant isolation, membership/Staff invariants, permissions, settings, branding/assets/PWA, hostname/DNS/TLS/domain lifecycle, Vue 3, Bootstrap/Sass and native Fetch remain intact. WP4 adds only focused permissions and services at established boundaries.

## D. Subscription Service Architecture

`SubscriptionService` owns commands; `SubscriptionQuery` owns current/history/future/event reads. The API controller is an envelope adapter. Each command resolves a Tenant, acquires the shared identity/membership lock and a Tenant subscription lock, begins a transaction, loads periods, validates plan/dates/operation/overlap/Trial/cap, inserts an immutable period and event, writes Audit, commits, and releases locks in `finally`. Sharing the membership lock serializes downgrade cap reads against Staff create/reactivate.

## E. Current / Historical / Future Entitlements

The query class uses UTC half-open evaluation: current is `starts_at <= now && ends_at > now`; history has `ends_at <= now`; future begins later. It never treats “latest” as current. Owner/Admin responses expose all three collections and chronological event evidence.

## F. Trial Lifecycle / Extension

Onboarding's immutable 30-day Trial remains unchanged. `EXTEND` requires the active Trial plan, a reason, a period beginning exactly at the latest Trial end, and 1–30 whole additional days. It appends a new Trial period plus `EXTENDED` event; it does not rewrite the original Trial.

## G. Paid Activation

`ACTIVATE` accepts active Starter/Growth only, explicit ISO-8601 dates, reason, and optional bounded external reference. It snapshots plan code, Staff limit, current controlled price and NGN currency, then atomically adds `CREATED` event and Audit. No card/payment processor data exists.

## H. Renewal

`RENEW` requires a prior period, retains its plan, rejects overlap, and appends `RENEWED` evidence. Existing periods are never edited.

## I. Upgrade

`UPGRADE` requires a target whose Staff snapshot is greater than the latest scheduled/effective plan and a non-overlapping period. Because the approved schema has no correction/reversal link that can immutably truncate a current period, “immediate” mid-period replacement is deliberately rejected as `SUBSCRIPTION_OVERLAP`; the Admin schedules it at the current end. This is the evidence-preserving interpretation required by the schema.

## J. Downgrade / Staff-Cap Protection

`DOWNGRADE` requires a lower target snapshot. While holding both the shared membership lock and Tenant subscription lock it counts active `STAFF` (never Owner). Above the target cap it returns `409 SUBSCRIPTION_STAFF_CAP_CONFLICT` with safe count/limit details. No Staff is modified. The check occurs at scheduling; the shared lock closes the Staff-activation race during the command. Operational transition acceptance requires rechecking capacity near the future boundary; access changes automatically by time without a background mutation. Concurrent subscription commands serialize; after the winner commits, overlapping competitors return `SUBSCRIPTION_OVERLAP` with no duplicate period/event/Audit.

## K. Expiry / Scheduled Operations

Access expiry is always time-derived. `subscriptions:record-expiries <platform-admin-user-id>` uses per-period advisory locks and an in-transaction repeat check to append at most one `EXPIRED` event and corresponding Audit. It never edits the subscription. Cron/Scheduler invokes Spark; no queue/broker/worker was added.

## L. Tenant Lifecycle

- Suspend: valid only from ACTIVE; records SUSPENDED, reason/time, and Audit; access immediately becomes BLOCKED.
- Reactivate: valid only from SUSPENDED; preserves Audit history, returns status to ACTIVE, and recomputes access (which may be READ_ONLY).
- Close: valid from ACTIVE/SUSPENDED, retains all data, writes terminal CLOSED/reason/time and Audit, and exposes no reopen command.

## M. Shop Lifecycle

Platform Admin can deactivate ACTIVE Shops or reactivate INACTIVE Shops by public Shop UUID with reason, timestamps and Audit. The target remains explicitly Shop-scoped and no data is deleted. Inactive means BLOCKED.

## N. Effective Access Policy

The existing `ShopContext` first requires active hostname plus authenticated active membership. `AccessGate` then applies CLOSED Tenant, suspended Tenant/inactive Shop, active lifecycle without current entitlement (READ_ONLY), and current entitlement (FULL), in that dominance order. Owner/Staff services reevaluate on each request; frontend state is advisory. Platform routes independently require Platform group, focused permission and recent MFA.

## O. Platform Admin Operational UI

The responsive Admin surface lists Tenant/Shop status, current period, future/history/events, active Staff, and offers activation, renewal, upgrade, downgrade, Trial extension, Tenant suspend/reactivate/close, and Shop deactivate/reactivate. It uses explicit UUID targets and reasons, includes terminal-close confirmation, and contains no impersonation, billing integration, analytics, or later-phase functions.

## P. Owner Subscription UI

The Owner sees current plan/window, access mode/explanation, active Staff, authoritative current snapshot limit, remaining capacity, scheduled periods and history. It contains no payment or mutation control.

## Q. API / Error Semantics

The established envelope is reused. Missing resources are 404; validation/reason/date input is 422; overlap, concurrency and invalid transitions are stable 409; Staff cap uses `SUBSCRIPTION_STAFF_CAP_CONFLICT`; lifecycle-derived mutation denial retains READ_ONLY/BLOCKED/CLOSED 403 behavior; Platform filters retain authentication, permission, MFA and throttling semantics.

## R. Audit Coverage Inventory

Verified statically: Owner/Tenant onboarding; Staff create/status/reset; grants/revokes; Shop settings; Admin MFA state; branding/profile/assets; domain request/rotation/verification/TLS/activation/primary/fallback/deactivation; subscription activation/renewal/upgrade/downgrade/extension/expiry; Tenant suspend/reactivate/close; Shop deactivate/reactivate. WP4 mutations place state/event/Audit in the same transaction.

## S. Central Audit Metadata Safety

`AuditMetadataPolicy` recursively rejects forbidden nested key patterns for credentials, MFA/recovery/magic links, verification tokens, TLS secrets, cookies, session/auth/bearer tokens, API secrets and binary/image bytes. It permits only scalars/arrays and bounds depth to four, entries to 40 per array, strings to 500 characters. `AuditService` applies it centrally and also trims/bounds reasons.

## T. Audit Transaction / Append-Only Protection

Audit policy/insertion exceptions propagate so owning WP4 transactions roll back. Application code exposes insert only—no Audit update/delete route or method. Database privilege verification was unavailable without MySQL. The operations guide gives exact owner `REVOKE UPDATE, DELETE`, `GRANT SELECT, INSERT`, and `SHOW GRANTS` commands; no trigger was invented. The narrow pre-migration MFA bootstrap table-existence exception remains and is not used by normal operational mutations.

## U. Tenant Isolation / IDOR-BOLA Review

Ordinary routes retain hostname + active membership scope. Platform operations use global public Tenant/Shop UUID targets but require Platform identity, focused permission and recent MFA; they never create Tenant membership or impersonation. Subscription references are queried under the resolved Tenant ID. Runtime two-Tenant attacks remain in the acceptance checklist because no database/runtime was available.

## V. Lifecycle + Custom-Domain Integration

Both platform and active custom domains resolve the same Tenant/Shop IDs. AccessGate lifecycle checks follow resolution, so suspension, closure and Shop inactivity apply equally to both. Domain evidence is not deleted by lifecycle transitions and hostname never grants authority.

## W. Responsive / Accessibility

WP4 uses wrapping card grids, labelled native date/select/text controls, break-safe timelines, touch-sized Bootstrap actions, explicit status copy and no wide table. Source structure supports 320/360/390 px, tablet, desktop, keyboard and 200% zoom. Browser evidence could not be captured or executed; screenshots were expressly not produced.

## X. Files Created

Text/source only:

- `app/Commands/RecordSubscriptionExpiries.php`
- `app/Controllers/Api/PlatformOperationsController.php`
- `app/Modules/Audit/Application/AuditMetadataPolicy.php`
- `app/Modules/Subscription/Application/SubscriptionQuery.php`
- `app/Modules/Subscription/Application/SubscriptionService.php`
- `app/Modules/TenantShop/Application/PlatformLifecycleService.php`
- `app/Support/SensitiveReason.php`
- `docs/operations/phase-01-subscriptions-and-lifecycle.md`
- `docs/Phase_1_Recovery_and_Completion_Work_Package_4_Delivery_Report.md`

## Y. Files Modified

- `README.md`
- `app/Config/AuthGroups.php`
- `app/Config/Routes.php`
- `app/Controllers/Api/ShopController.php`
- `app/Modules/Audit/Application/AuditService.php`
- `app/Modules/BrandingDomain/Application/DomainService.php`
- `app/Modules/Identity/Application/PermissionService.php`
- `app/Modules/Identity/Application/StaffService.php`
- `app/Views/admin/index.php`
- `app/Views/shop/index.php`
- `resources/css/app.scss`
- `resources/js/admin/app.js`
- `resources/js/api/phase-one.js`
- `public/assets/css/app.css`
- `public/assets/js/admin/app.js`
- `public/assets/js/api/phase-one.js`
- `docs/audit-contract.md`
- `docs/configuration.md`
- `docs/database/migration-register.md`
- `docs/database/schema-notes.md`
- `docs/dependencies.md`
- `docs/manual-verification-phase-01.md`
- `docs/modules/phase-01-completion.md`
- `docs/modules/phase-01.md`
- `docs/security/permission-catalogue.md`

## Z. Files Removed

None.

## AA. Database / Migration Changes

No WP4 schema change was required; the approved Phase 1 schema remained sufficient.

## AB. Zero-Binary Verification

- new images: `0`;
- new binary files: `0`;
- screenshots: `0`.

The manual PWA files remain absent/external. Changed paths were checked for prohibited extensions, NUL bytes, Git binary diffs, and newly added embedded image/Base64/SVG content.

## AC. Documentation Updated

README, module completion/contracts, permission catalogue, Audit contract, consolidated manual verification, and a new subscription/lifecycle operations guide now match implemented permission names, commands, immutable semantics, transitions, append-only deployment procedure and actual verification status.

## AD. Static Verification

All PHP application files were syntax checked. Changed source/published JavaScript was syntax checked and compared. Composer metadata, Git whitespace, route/controller/API references, prohibited Phase 2 terms in new application code, generated-source parity, and zero-binary properties were inspected. The frontend build completed with upstream Sass deprecation warnings.

## AE. Runtime Verification

Attempted `php spark routes`; application boot failed before CodeIgniter because `vendor/` is absent. PHP has `gd`, `intl`, `mysqli`, and `pdo_mysql`, but no MySQL client/server executable or configured database was available. Therefore no HTTP, Shield, session, DNS, upload, lifecycle or browser workflow was claimed executed.

## AF. Database Verification

Not executed: no MySQL client/server or configured disposable MySQL 8.4 database exists in this environment. Schema SQL was statically inspected: approved tables/checks/FKs/indexes/generated guards/collations remain unchanged. Fresh migrate/seed/rollback/reapply, `SHOW CREATE TABLE`, repeat seed, transactional failure, advisory-lock and privilege checks remain owner-environment acceptance.

## AG. WP1 Runtime Debt Rechecked

Vendor absence still blocks CodeIgniter/Shield routes, Email2FA, CSRF, throttle and session execution. Configuration/source checks remain intact; no runtime pass is claimed.

## AH. WP2 Runtime Debt Rechecked

Onboarding, forced rollback, Staff concurrency/caps, recovery/session invalidation, permissions and two-Tenant attacks remain runtime-blocked by vendor/database absence. WP4 shares the existing membership lock for subscription downgrade serialization.

## AI. WP3 Runtime Debt Rechecked

PHP GD/intl extensions are present, but app/database/DNS/browser execution remains unavailable. No test image or screenshot was generated. Dynamic manifest/domain/upload code was statically preserved.

## AJ. WP4 Manual Verification Results

Service/controller/route/UI wiring and state rules were statically inspected. PHP/JS/build/text/binary checks executed. Subscription concurrency, MySQL advisory locks, Audit rollback/privileges, lifecycle HTTP behavior and responsive interaction could not execute without application/database/browser infrastructure.

## AK. Complete Phase 1 End-to-End Acceptance Matrix

| Area | Scenario | Expected | Actual | Status |
|---|---|---|---|---|
| Runtime | CodeIgniter route boot | Routes enumerate | Failed before boot: `vendor/` absent | Environment blocked |
| Database | Fresh migrate/seed/rollback/reapply | Valid MySQL 8.4 schema | No MySQL runtime | Environment blocked |
| Platform auth | Login, Email2FA, MFA, throttle, logout | Secure Admin boundary | Source inspected only | Environment blocked |
| Onboarding | Success and forced failures | Atomic graph or rollback | Source inspected only | Environment blocked |
| Owner/Staff | Lifecycle, caps, reset, permissions | Isolated authoritative state | Source inspected only | Environment blocked |
| Branding/PWA | Real uploads and manifest | Safe branded presentation | GD/intl present; app unavailable | Environment blocked |
| Domains | DNS through fallback | Valid state machine/Audit | No controlled DNS/app | Environment blocked |
| Subscription | Activate/renew/upgrade/downgrade/extend | Immutable serialized evidence | Implementation/static checks complete | Runtime pending |
| Subscription | Above-cap Starter downgrade | 409, no Staff change | Guard/lock inspected | Runtime pending |
| Expiry | Repeat command | One event, time-derived access | Command syntax inspected | Runtime pending |
| Lifecycle | Tenant/Shop transitions | FULL/READ_ONLY/BLOCKED/CLOSED | Composition inspected | Runtime pending |
| Audit | Secret policy and failure rollback | Reject and rollback | Central policy inspected | Runtime pending |
| Isolation | Two-Tenant ID/host manipulation | No disclosure/mutation | Scoping inspected | Runtime pending |
| Responsive | 320/360/390/tablet/desktop/zoom | Complete usable cards/forms | Source inspected; no browser | Environment blocked |
| Binary rule | Changed-file audit | Zero new binaries/images | Passed | Pass |

## AL. Remaining Environment-Only Verification

Composer dependency restoration; disposable MySQL 8.4 migration/seed/rollback and grants; Shield/Email2FA/mail/session/CSRF/throttle HTTP flows; transaction/advisory-lock concurrency; controlled DNS/TLS; real operator-provided uploads; two-browser/two-Tenant attacks; browser widths/keyboard/zoom; and production runtime DB privilege proof.

## AM. Remaining Implementation Gaps

No known Phase 1 implementation gap remains.

## AN. Deviations / Blockers

Mid-period immediate upgrade/downgrade is rejected rather than truncating immutable evidence because the approved schema has no correction/reversal mechanism for a period. Operators schedule the transition at the existing end. This is not an unimplemented path; it is the least-destructive schema-compliant behavior. Runtime acceptance is blocked by missing Composer vendor, MySQL, controlled DNS and browser—not a known code-scope blocker.

## AO. Phase 1 Final Assessment

**IMPLEMENTATION COMPLETE — OWNER-ENVIRONMENT ACCEPTANCE PENDING**

All four approved work packages have implementation and static evidence, and no known Phase 1 code gap remains. Full acceptance cannot honestly be classified as ready until the explicitly listed runtime, database, DNS, concurrency, isolation and responsive checks execute successfully in the owner environment.

## AP. Phase 2 Readiness

Phase 2 was not started. Phase 1 is ready for owner-environment acceptance and owner approval; Phase 2 must remain unauthorized until that review is complete.

## AQ. Git Commit / Change Summary

WP4 adds immutable serialized subscription operations, lifecycle controls, final Owner/Admin presentation, central Audit safety, expiry evidence, documentation reconciliation and consolidated acceptance classification. The final commit and PR metadata are recorded after all available checks.
