# DawaTrack — Phase 9 Strategic Implementation Plan

**Phase:** 9 — Platform Administration and SaaS Operations  
**Plan status:** Draft for Owner review and approval; no implementation is authorized by this document.  
**Prepared against:** current `main` source at the time of drafting.  
**Controlling foundations:** Business & Product Blueprint; Technical Blueprint v1.0; Database / Schema Design v1.1; Strategic Phased Implementation Roadmap v1.1; current module map, security foundation, permission catalogue, Phase 1 subscription/lifecycle operations, Phase 2 Audit/support contracts and Phase 7 Device operations.

---

## 1. Executive purpose

Phase 9 completes the separate, online-only Platform Administration experience required to operate DawaTrack as a SaaS platform. It consolidates the Tenant, Shop, Owner, Subscription, Domain, Device and Audit capabilities established in earlier phases into one safe, auditable operational console.

The outcome is not a second business application or a privileged way to work inside Shops. Platform operators manage platform lifecycle and provide explicitly authorized diagnostics. They do not become Shop members, inherit Shop permissions, silently impersonate Owners, or record/change Sales, Stock, Debt, Cash or Reconciliation.

Phase 9 must be delivered as end-to-end vertical slices. Each work package includes the applicable authoritative data/query, owning service, Platform authorization, API, Admin UI, Audit, error/recovery behavior, documentation and manual verification. Existing foundation services are reused and completed where necessary; they are not replaced merely to create a new Admin implementation.

## 2. Source authority and interpretation

The documents below govern this plan in the following order:

1. **Business & Product Blueprint:** product outcome, user roles, operating model and intended user experience.
2. **Technical Blueprint v1.0:** modular-monolith architecture, separate Platform Admin authority/session boundary, online-only Admin interface, support-context restrictions and platform security.
3. **Database / Schema Design v1.1:** authoritative persistence model, immutable entitlement periods/events, existing Tenant/Shop/identity/domain/device/Audit records, constraints, indexes and transaction/concurrency rules.
4. **Strategic Phased Implementation Roadmap v1.1, Phase 9:** the exact Phase 9 scope, dependencies, deliverables, checklist and Definition of Done.
5. **Existing repository contracts:** `docs/modules/module-map.md`, `docs/security/foundation.md`, `docs/security/permission-catalogue.md`, `docs/operations/phase-01-subscriptions-and-lifecycle.md`, Phase 2 Audit/support contracts and `docs/operations/phase-07-wp1-devices.md`.

This plan elaborates the Phase 9 roadmap into sequenced work packages. It does not override the foundations or silently expand scope. Where the repository's existing code and a foundation contract differ, implementation must stop at that point, document the discrepancy and obtain Owner approval before changing the contract.

### 2.1 Explicit Phase 9 scope from the roadmap

- Platform dashboard with real platform metrics.
- Tenant search and detail.
- Shop registration/onboarding progress.
- Branding and domain management.
- Primary Owner creation, recovery and reassignment.
- Entitlement renewal, upgrade and downgrade.
- Tenant/Shop suspension, reactivation and closure.
- Device and synchronization-health entry points.
- Explicit reasoned support context, read-only/diagnostic by default.
- Platform Audit and support procedures.

### 2.2 Existing foundations to reuse

The repository already contains Tenant/Shop, membership, branding/domain, subscription, Audit and Device modules/services, Platform Admin filters, MFA, platform permissions, selected controllers/routes, onboarding/lifecycle/subscription operations and Device readiness. Phase 9 is not permission to duplicate them. WP1 inventories and reconciles what exists; subsequent packages close user-facing and end-to-end gaps in their owning modules.

Existing permission codes include `platform.subscriptions`, `platform.tenants`, `platform.audit` and `platform.audit_support`. They remain assigned only to the `platform_admin` authority as defined in the permission catalogue. Any new Platform permission requires an explicit foundation-backed justification and Owner approval; do not add a broad `platform.manage` or `allow all` permission.

## 3. Non-negotiable foundation constraints

### 3.1 Architecture and technology

- One CodeIgniter 4 modular monolith and one MySQL 8.4 LTS/InnoDB authoritative database.
- Platform Admin is a distinct online-only interface within the same CI4 application, using the separate Admin route/session/authority boundary.
- Vue 3 Composition API with normal `setup()`, ES6+, native Fetch through the existing API layer, statically compiled Bootstrap CSS and server-selected application workspaces.
- No TypeScript, Vue Router, Pinia, GraphQL, microservices, generic repository redesign, JavaScript application bundler, Docker/Kubernetes-first redesign or new queue/broker.
- Scheduled work follows scheduler/cron → CI4 Spark command → owning service. No unapproved queue/worker infrastructure.
- Existing code conventions, strict typing, focused services, thin controllers and generated asset workflow remain mandatory.

### 3.2 Identity, tenancy and authority

- Shield establishes identity. The `platform_admin` group/platform permissions establish Platform authority. Tenant membership establishes Tenant authority. These are separate.
- Platform Admin never receives synthetic membership in every Tenant and never gains Shop Owner/Staff authority.
- Hostname, Tenant UUID, Shop UUID, browser state or client-supplied capability flags never grant authority.
- Every request and mutation rechecks the authenticated Platform Admin, required permission and recent MFA server-side. Sensitive actions use the established step-up/MFA policy and rate limits.
- No silent impersonation. Platform Admin cannot post Shop transactions or mutate operational business evidence.

### 3.3 Data integrity, immutability and transactions

- MySQL remains canonical. Reuse existing Tenant, Shop, Shield User, Membership, Branding, Domain, Subscription, Device and Audit records.
- No duplicate Admin Tenant/Shop/User/Subscription tables or parallel status/balance projections.
- Subscription periods are immutable half-open intervals `[starts_at, ends_at)`; renewal/upgrade/downgrade/extension creates a new period and event. Time-derived expiry does not mutate an entitlement row.
- Existing Tenant/Shop lifecycle state transitions remain explicit and terminal closure is not reversed.
- Owner reassignment is serialized and must preserve the one-active-Primary-Owner invariant. Credential recovery/session invalidation is deliberate and observable.
- Each sensitive mutation, its domain evidence, required Audit and required change publication participate in the same transaction where the foundation requires it. Failed transactions leave no partial state or false Audit.
- Append-only Audit remains immutable. Never reconstruct missing Audit history retrospectively as a substitute.

### 3.4 Support boundary

- Support is an explicit, reasoned, narrow, expiring diagnostic context. It is read-only by default and visibly attributed.
- Normal Platform Audit and Tenant-support Audit remain distinct.
- Support does not create Tenant membership, an impersonated identity or a durable hidden session.
- A support read must be authorized, bounded, redacted, rate-limited and evidenced according to the Phase 2 support contract.
- Any exceptional action must call the owning domain service with its own permission, reason, confirmation, transaction and Audit. Support context itself does not grant mutation authority.

### 3.5 Device and synchronization boundary

- Phase 9 may list/revoke eligible Phase 7 Registered Devices and show the existing generation/change/readiness information that is already authoritative.
- Phase 9 does not implement Phase 10 Device provisioning, pull cursors, Sync Receipts, outbox transport, conflict processing, offline Admin operations or generation recovery.
- Do not label a Shop/device as “sync healthy” based on an unverified heartbeat or a UI-only status. Show only fields and readiness facts supported by current server evidence, with freshness and “not yet available” distinctions.
- Admin requires connectivity. No offline Platform Admin actions or authenticated Admin data caching.

### 3.6 Database scope

The Phase 9 roadmap explicitly says no separate Admin business tables are invented. Use existing Tenant/Shop/identity/subscription/domain/device/Audit tables. Add an index only if query review/measurement demonstrates a need and the index is approved, paired with a CI4 migration and equivalent raw MySQL SQL script, register/schema notes, rollback notes and rehearsal. Do not alter historical migrations or schema v1.1 semantics.

## 4. Work-package structure and dependency sequence

The recommended delivery is seven sequential packages. Each package is separately reviewed, locally verified and Owner-accepted before the next package starts. Cross-package dependencies are explicit; a later package must not conceal an incomplete earlier contract.

| Package | Name | Primary outcome | Depends on |
|---|---|---|---|
| WP1 | Platform Admin Foundation, Dashboard and Tenant Discovery | Secure Admin shell, real platform dashboard, bounded Tenant search/detail | Phases 1–8 |
| WP2 | Tenant Onboarding and Primary Owner Lifecycle | Complete onboarding operations, Owner creation/recovery/reassignment | WP1 |
| WP3 | Subscription and Entitlement Operations | Complete plan/period/event operations and clear access outcomes | WP1; WP2 onboarding |
| WP4 | Branding, Domain and Shop Identity Operations | Safe brand/domain lifecycle and operational verification | WP1; existing BrandingDomain services |
| WP5 | Tenant/Shop Lifecycle and Device Operations | Safe suspension/reactivation/closure and Phase 7 Device administration | WP1; WP2; WP3; Phase 7 |
| WP6 | Support Context and Platform Audit | End-to-end reasoned support and distinct Platform Audit experiences | WP1; Phase 2 Audit foundation; WP5 |
| WP7 | Cross-package Integration, Security Hardening and Phase 9 Closure | Complete end-to-end operator journeys, evidence and handover | WP1–WP6 |

A work package may be split into smaller implementation tasks/commits, but its acceptance boundary and scope may not be weakened.

---

## 5. Detailed work-package plans

## WP1 — Platform Admin Foundation, Dashboard and Tenant Discovery

### Objective
Deliver the distinct, secure Platform Admin entry experience and an accurate operational view of DawaTrack, with searchable Tenant discovery and bounded Tenant detail.

### Scope and deliverables
1. Inventory the current Platform Admin routes, filters, Shield group/permissions, MFA freshness enforcement, session separation, controllers, services and UI before changing code.
2. Complete the Admin shell/navigation and responsive layout without sharing Shop navigation authority or Shop session assumptions.
3. Build a platform dashboard from authoritative queries: registered Tenants/Shops, active Tenants, Trial/Active/Expired entitlement counts, suspended Tenants, platform users, registered Devices, synchronization/readiness indicators and upcoming/past entitlement expiries where available.
4. Make every metric definition explicit (status source, time boundary, freshness, exclusions). Do not expose cross-Tenant Sales, Cash, Debt, Stock valuation or Profit analytics.
5. Provide bounded Tenant search/filter/pagination and a safe Tenant detail summary: Tenant/Shop identity and status, onboarding progress, current/historical/future entitlement summary, active Staff count and available administrative actions.
6. Add loading, empty, validation, server-error, permission-denied, expired-MFA, offline and stale/fresh states.

### Backend and data
- Use focused Platform query objects/services; avoid loading every Tenant and N+1 lookups.
- Use keyset or bounded pagination consistent with existing repository conventions; allowlisted filters and deterministic ordering.
- Query authoritative Tenant/Shop/Subscription/User/Device data; do not add summary tables or cached counts without approved measured need.
- Return only fields required by the Admin UI; never expose internal primary keys, credential material, password/security tokens, private Shop business data or raw Audit metadata.
- Add indexes only after query review proves need and the Owner approves the paired schema/SQL change.

### Security and Audit
- Every route is protected by the Platform Admin boundary; every sensitive read requires the correct Platform permission and recent MFA as prescribed.
- Dashboard and Tenant search are not a support context and must not silently open Tenant business data.
- Audit sensitive Admin searches/details if required by the existing Audit vocabulary/contract; document the event semantics before implementation.
- No Tenant mutation is added in WP1 beyond existing explicitly authorized read behavior.

### UI
- Admin-specific branding and clear “Platform Administration” identity.
- Large-screen efficient tables and phone/tablet cards with filters, status labels, accessible detail drill-down and visible freshness.
- No dead buttons, placeholder metrics or mock values.

### Manual verification
- Verify every dashboard count against authoritative fixtures and direct approved queries.
- Test zero Tenants, large Tenant population, no Device data, no subscriptions, expired MFA, missing permission and offline states.
- Search by approved identifiers/status and confirm pagination does not duplicate/skip records.
- Attempt Tenant/Shop UUID and host manipulation; confirm no authority escalation.
- Confirm dashboard never exposes Tenant Sales/Cash/Debt/Profit data.

### Acceptance gate
The Admin shell is distinct and secure; all displayed metrics are real and defined; Tenant discovery is bounded, scoped to platform administration and safe; phone/desktop flows work.

---

## WP2 — Tenant Onboarding and Primary Owner Lifecycle

### Objective
Make Tenant registration/onboarding and Primary Owner lifecycle fully operable from the Platform Admin console using existing authoritative services.

### Scope and deliverables
1. Guided Tenant/Shop registration with validation and clear progress across the existing atomic onboarding steps.
2. Safe creation or identification of the Shield User for the Primary Owner, Owner membership creation and invitation/credential recovery consistent with the existing identity/recovery model.
3. Onboarding progress/status detail that distinguishes completed, pending, blocked and failed prerequisites without claiming activation prematurely.
4. Primary Owner recovery and reassignment, with explicit reason, confirmation, current-state review and transaction serialization.
5. Former Owner membership/session invalidation and clear confirmation of the resulting authority state.
6. A recoverable interrupted-onboarding support path that resumes through owning services and does not create duplicate Tenants, Shops, domains, memberships, Trials or Audit events.

### Backend invariants
- Reuse `OnboardingService`, Identity, TenantShop, BrandingDomain and Subscription services; controllers remain transport-only.
- Preserve the Phase 1 one-Shop invariant unless the authoritative schema/roadmap is explicitly revised and approved.
- Enforce same-Tenant relationships and one active Primary Owner under deterministic locking/uniqueness.
- Never expose or email raw passwords; do not create an alternate credential store. Recovery uses the approved Shield flow.
- Every Owner creation, recovery, reassignment and membership/security change is reasoned and atomically audited; session/cache invalidation occurs only after successful commit according to existing cache-revocation contract.
- Failed operations leave no partial Tenant/Shop/Owner/Trial/Audit state.

### UI and manual verification
- Complete onboarding from an Admin account on phone and desktop.
- Test duplicate business/domain/user attempts, invalid inputs, interrupted/failed transaction, retry, and concurrent Owner reassignment.
- Confirm exactly one active Primary Owner and exactly one Shop per Tenant.
- Verify former Owner access/session invalidation and new Owner recovery.
- Confirm no direct DB edit is required for normal onboarding/recovery.

### Acceptance gate
A Platform Admin can complete and safely recover the full Tenant onboarding and Owner lifecycle through the application, with no hidden or duplicate identity state.

---

## WP3 — Subscription and Entitlement Operations

### Objective
Deliver a complete operator workflow for activation, renewal, upgrade, downgrade, Trial extension and expiry review, preserving immutable entitlement history and the existing access gate.

### Scope and deliverables
1. Tenant entitlement timeline showing current, historical and scheduled periods, plan snapshots, effective boundaries, events and bounded external reference.
2. Guided activation/renewal/upgrade/downgrade/Trial extension form with plan, dates, amount/reference where permitted, reason and confirmation.
3. Clear preview of the resulting effective period and access mode before commit.
4. Explicit handling of invalid overlap, invalid transition, closed Tenant, inactive plan and Staff-cap downgrade conflict.
5. Expiry operations view and repeat-safe expiry-event recording through the existing scheduler/cron → Spark command → Subscription service pattern.
6. Plain-language explanation of FULL, READ_ONLY, BLOCKED and CLOSED outcomes for the Tenant and Shop.

### Backend invariants
- Reuse `SubscriptionService` and `SubscriptionQuery`; do not mutate existing `subscriptions` rows.
- New period, `subscription_events`, Audit and required change publication commit atomically.
- Use existing Tenant-specific subscription lock and membership lock ordering. Recheck active Staff count for downgrade; never auto-deactivate Staff.
- Entitlement periods remain half-open UTC intervals. Current status is derived from time and Tenant/Shop state; no mutable status projection is introduced.
- Manual paid activation records an approved bounded external reference only; no payment-card data or unapproved payment gateway.
- Expiry is time-derived. Repeat-safe event recording must not change entitlement history or grant/extend access.

### Manual verification
- Activate, renew, upgrade, downgrade and extend Trial using valid/invalid boundaries.
- Confirm no overlap, immutable prior periods, correct supersession/event history and immediate access evaluation.
- Attempt downgrade with active Staff above target cap; verify refusal and no Staff auto-deactivation.
- Test future period, expired period, suspended Tenant and closed Tenant.
- Repeat expiry command; verify no duplicate EXPIRED event/Audit and no access change.

### Acceptance gate
Every subscription operation is traceable and immutable; the access gate matches the effective period and Tenant/Shop state; operators do not need direct DB edits.

---

## WP4 — Branding, Domain and Shop Identity Operations

### Objective
Complete safe Admin-side visibility and management of Tenant/Shop branding and verified domains, using the existing BrandingDomain ownership and verification lifecycle.

### Scope and deliverables
1. Searchable domain inventory linked to Tenant/Shop with type, verification, TLS/certificate, primary/active state and last-known status/freshness.
2. Tenant/Shop brand preview and asset metadata view; safe brand update/replacement workflow only where the existing service contract permits Platform Admin action.
3. Guided custom-domain challenge and verification progress; explicit ownership verification and TLS readiness steps.
4. Activate, set primary, deactivate and fallback operations through existing domain service transitions.
5. Clear handling of DNS propagation, failed ownership verification, certificate pending/failed and conflicting primary-domain attempts.
6. Safe asset replacement status and history with versioned URLs and no executable upload paths.

### Backend invariants
- Reuse `BrandingService`, `DomainService`, hostname policy/resolver and existing tables.
- Hostname knowledge never grants Tenant access; platform/custom domains resolve to the same authoritative Tenant/Shop.
- Verification challenge secrets are hashed; never reveal raw tokens after creation or store TLS private keys in MySQL.
- Validate decoded file bytes/MIME allowlist, size/dimensions, optimize safely, opaque filenames, non-executable storage and safe serving.
- Domain/brand mutations require correct Platform authority, recent MFA where prescribed, reason/confirmation and atomic Audit.
- Do not bypass ownership proof or activate a domain merely because DNS text appears in the UI.

### Manual verification
- Verify valid/invalid/expired challenge, ownership failure, TLS not ready, successful activation and primary switch.
- Confirm old primary fallback and host presentation; verify no authority changes across hosts.
- Test malformed/oversized/executable image upload and safe replacement.
- Confirm branded login/title/favicon/manifest remain correct and readable on phone/desktop.

### Acceptance gate
Brand/domain operations are safe, verifiable, auditable and use the same Tenant/Shop identity; no host or asset path bypass exists.

---

## WP5 — Tenant/Shop Lifecycle and Device Operations

### Objective
Provide a clear Admin workflow for Tenant/Shop suspension, reactivation and closure, plus the Phase 7 Registered Device security/readiness operations allowed before Phase 10.

### Scope and deliverables
1. Tenant and Shop status detail with lifecycle history and reason.
2. Confirmed, reasoned suspend/reactivate/close Tenant and deactivate/reactivate Shop workflows.
3. Clear preview of the impact on Shop access and sessions before the action.
4. Device inventory linked to Tenant/Shop: safe public identity, display name, active/revoked status, last-seen/last-validated timestamps and revocation reason where authorized.
5. Revoke eligible registered Device with reason and show the effect without deleting history.
6. Show current supported generation/change/readiness facts and clearly label future Phase 10 transport/health details as unavailable/not implemented.

### Backend invariants
- Reuse `PlatformLifecycleService`, `DeviceService`, `DeviceQuery`, `DeviceSchema`, AccessGate, Audit and ChangeSequence.
- Preserve allowed transitions: Tenant ACTIVE→SUSPENDED; SUSPENDED→ACTIVE; ACTIVE or SUSPENDED→CLOSED (terminal). Shop ACTIVE↔INACTIVE.
- Lifecycle actions are serialized, expected-state guarded, reasoned, audited and publish required changes atomically.
- Device revoke preserves records and Audit; never reveal credential hashes, cookies, installation secrets or internal IDs.
- No Phase 10 provision, cursor, receipt, outbox, offline operation or generation recovery is implemented in Phase 9.

### Manual verification
- Suspend/reactivate/close Tenant and deactivate/reactivate Shop; test invalid transitions and concurrent attempts.
- Confirm Shop requests/sessions/access mode respond immediately and consistently across platform/custom hosts.
- Revoke active Device; confirm proof no longer validates and history remains.
- Verify no secret appears in Admin response, logs, screenshot or Audit UI.
- Confirm Phase 10-only health data is not fabricated.

### Acceptance gate
Lifecycle transitions and Device security operations work end-to-end without deletion, hidden access or Phase 10 scope leakage.

---

## WP6 — Support Context and Platform Audit

### Objective
Complete the operator's reasoned diagnostic workflow and distinct Platform Audit experiences without impersonation or covert Tenant access.

### Scope and deliverables
1. Platform Audit search/history with bounded filters, pagination, actor, action, time, Tenant/Shop/resource and correlation drill-down.
2. Separate platform-wide Audit view from Tenant-support Audit view.
3. Explicit support-context initiation with selected Tenant/Shop, required reason, limited purpose, expiry and recent MFA.
4. Persistent visible support banner with selected scope, actor, purpose, expiry/countdown and exit action; no hidden context.
5. Read-only/diagnostic data access only through explicitly authorized support query paths, bounded DTOs and redaction.
6. Support-context expiry, exit, timeout and attempted out-of-scope access handling.
7. Audit the support-context establishment and sensitive Audit queries per the Phase 2 contract. Record attribution on every supported diagnostic read as specified by that contract.

### Backend invariants
- Reuse `AuditService`, Audit query/repository and the existing `platform.audit` / `platform.audit_support` permissions.
- Normal Platform Audit and support Audit remain distinct; recent MFA required.
- Support scope is server-bound to the Admin, Tenant/Shop, purpose/reason and expiry; continuation cannot be replayed by another Admin or outside the permitted period.
- No Tenant membership, impersonated identity, durable hidden support session or mutation authority is created.
- Redact Customer/private data, credential/security material, blind-count data and sensitive financial details unless the approved support DTO explicitly permits them.
- Support access is rate-limited and correlation logged. Sensitive actions invoked from support must use their owning domain service and separate authority; support context alone never authorizes them.

### Manual verification
- Start support with valid/invalid reason, wrong Tenant/Shop, expired MFA and missing permission.
- Confirm banner attribution, exact scope, expiry, exit and automatic expiration.
- Attempt to switch Tenant/Shop, replay context as another Admin, invoke write routes or access forbidden business fields; verify denial and Audit evidence.
- Compare normal Platform Audit and support Audit; verify filters, pagination, redaction and immutable behavior.
- Confirm support access leaves no membership/impersonation or business mutation.

### Acceptance gate
Operators can investigate safely with explicit, expiring, read-only support context and useful immutable Platform Audit, without silent impersonation.

---

## WP7 — Cross-package Integration, Security Hardening and Phase 9 Closure

### Objective
Prove that the complete SaaS operations journey works end-to-end and hand over safe procedures for operating DawaTrack.

### Integration journeys
1. Platform Admin login → MFA → dashboard → Tenant search → Tenant detail.
2. New Tenant → Shop/branding/platform domain → Primary Owner → Trial → successful Owner access.
3. Owner recovery/reassignment → old access invalidated → new Owner access confirmed.
4. Trial expiry → READ_ONLY → renewal/upgrade → FULL; verify no overlap and correct event/Audit history.
5. Downgrade over Staff cap → refusal with no automatic Staff deactivation.
6. Suspend → Shop BLOCKED; reactivate → access reevaluated; close → terminal CLOSED behavior.
7. Domain verification/TLS → activate → primary switch/fallback with unchanged Tenant authority.
8. Support initiation → visible reasoned read-only diagnostics → Audit → expiry/exit.
9. Device revoke → proof invalidated → retained Device/Audit history.
10. Platform Admin session/MFA expiry → denied step-up → reauthentication; Shop session and Platform Admin session remain separate.

### Cross-cutting hardening
- Review every Platform route for filter, permission, recent MFA, CSRF, throttle and safe error behavior.
- Review all Tenant/Shop selectors and joins for explicit server-side scope and non-enumerating foreign-resource outcomes.
- Review query bounds, N+1 behavior, pagination stability and index use. Capture EXPLAIN/EXPLAIN ANALYZE where supported and useful; do not claim performance without measurements.
- Verify all mutations have reason/confirmation, atomic Audit and correct change publication.
- Verify concurrent Owner reassignment, subscription transition, lifecycle change, domain primary switch and repeated submissions.
- Verify no dead controls, placeholder metrics, mock values, unsafe uploads or raw secrets.
- Confirm all static assets are generated from editable resources using the existing build process.

### Documentation and handover
Update, as applicable:
- Phase 9 work-package contracts, source/requirement matrix, manual verification registers and delivery reports.
- `docs/modules/module-map.md`, relevant module docs and API conventions.
- `docs/security/foundation.md`, `docs/security/permission-catalogue.md` and Admin route/session/MFA policy notes.
- `docs/database/schema-notes.md`, migration register and paired SQL only if an approved measured index/schema change exists.
- Subscription, Domain, Device, Audit/support and Platform Admin operations runbooks.
- Configuration, deployment, backup/recovery and support escalation notes.
- Strategic roadmap status and Phase 9 closure/acceptance record.

### Manual verification and Definition of Done
Phase 9 is complete only when every integration journey passes on a representative prior-phase database and the intended deployment topology; all relevant cases have actor/role, Tenant/Shop, data fixture, steps, expected result, observed result, timestamp, evidence reference and reviewer recorded; no unresolved critical/high security or data-integrity issue remains; every sensitive action is audited; phone/tablet/desktop workflows work; and the Owner explicitly accepts the complete phase.

No automated test framework or suite is to be introduced. Manual verification is the approval authority, supported by syntax/static checks, migration rehearsal, query-plan review and security inspection.

### Explicit exclusions
- Phase 10 offline sync transport, provision, pull cursor, Sync Receipts, outbox, conflict processing and generation recovery.
- Offline Platform Admin or caching of authenticated Admin data.
- Silent impersonation or synthetic Tenant memberships for Platform Admin.
- Platform Admin use of Shop workflows or access to cross-Tenant operational financial analytics.
- New Admin business tables, duplicate Tenant/Shop/User/Subscription records or mutable entitlement history.
- New payment gateway, payment-card storage or automated billing collection.
- Generic RBAC redesign or broad super-permission.
- Data warehouse, analytics/forecasting, microservices, queues/brokers or infrastructure redesign.
- Production deployment/operational release without a separate Owner decision.

---

## 6. Cross-package acceptance gates

Every WP follows the same sequence:

1. **Plan traceability:** map every roadmap/foundation requirement to code owner, endpoint, UI, Audit behavior, manual case and evidence.
2. **Source inventory:** inspect current code and schema before implementation; classify existing complete, partial, missing, defective and out-of-scope capabilities.
3. **Contract approval:** define inputs/outputs, authority, transaction/lock order, Audit, change publication, error codes, UI states and manual cases before coding.
4. **Implementation:** vertical slice in the owning module; no unrelated refactor or scope expansion.
5. **Engineering review:** strict typing, focused services, query bounds, security, concurrency, accessibility, phone/tablet/desktop and generated assets.
6. **Manual verification:** execute the WP register on the local environment; retain evidence and actual outcomes.
7. **Owner review:** present changed files, tests/checks, manual results, known limitations and exact commit/PR. Stop and wait for explicit acceptance.
8. **Integration:** merge only accepted WPs in dependency order, with clean ancestry and no duplicate changes.
9. **Closure:** phase-wide integration journeys, documentation, operations handover and separate deployment decision.

No work package starts until the previous package is accepted, unless the Owner explicitly authorizes a documented parallel dependency.

## 7. Required Phase 9 planning artifacts

Before WP1 implementation is authorized, prepare and approve:

- This Phase 9 Strategic Implementation Plan.
- Phase 9 requirement-to-work-package traceability matrix.
- Phase 9 module/source inventory (existing vs missing vs change required).
- WP1 detailed implementation contract and API/UI/manual verification plan.
- Cross-package permission/MFA/session matrix.
- Cross-package transaction, lock-order and Audit/change-publication matrix.
- Phase 9 manual verification master register and evidence template.
- Risk/dependency register and explicit Phase 10 boundary register.

Each later WP must have its own detailed contract and manual verification guide derived from the approved strategic plan before implementation begins.

## 8. Key risks and controls

| Risk | Control |
|---|---|
| Rebuilding Phase 1 functionality and diverging from foundation | Mandatory source inventory; reuse owning services; no duplicate tables |
| Admin privilege leaks into Tenant authority | Separate Shield platform group, session/filter, explicit Tenant selection and no membership/impersonation |
| Support becomes covert impersonation | Explicit reason, scope, expiry, visible banner, read-only DTOs and Audit |
| Subscription overlap or accidental access extension | Immutable half-open periods, tenant lock, transaction and event/Audit atomicity |
| Downgrade silently disables Staff | Refuse above plan cap; never auto-deactivate |
| Owner reassignment race leaves zero/two Owners | Deterministic serialization, one-active-Owner invariant and session invalidation |
| Lifecycle action leaves Shop sessions usable | Owning AccessGate/session invalidation, cross-host checks and immediate state re-evaluation |
| Domain takeover/host-based authorization | Hashed challenge, ownership/TLS checks; host never grants authority |
| Device secrets leak through Admin UI/logs | Safe DTO allowlist; no credential hash/cookie/installation proof exposure |
| Misleading device/sync health before Phase 10 | Show only current evidence and mark future transport health unavailable |
| Unbounded Tenant dashboard/search | Bounded queries, stable pagination, query review and measured indexes only |
| Sensitive action commits without Audit | Same-transaction Audit and failure/rollback verification |
| Admin data cached/offline | Online-only interface; no authenticated Admin caching |
| UI looks complete but has dead controls | No placeholders; each visible action verified end-to-end |

## 9. Plan-level Definition of Done

Phase 9 may be marked complete only when:

- All seven work packages are individually Owner-accepted.
- The Admin interface is distinct, online-only and MFA-protected.
- All Phase 9 scope in the Strategic Roadmap is delivered end-to-end.
- Tenant onboarding, Owner lifecycle, subscription, domain, lifecycle, Device and support journeys use their authoritative owning services.
- No hidden Tenant membership, impersonation, cross-Tenant financial surveillance or privilege bypass exists.
- All sensitive actions and support reads have the required Audit evidence.
- Subscription history and Audit remain immutable; concurrency and rollback behavior are verified.
- Device/sync presentation does not claim Phase 10 capabilities prematurely.
- No unsupported schema/table/permission/dependency or architectural redesign is introduced.
- Manual verification and evidence registers are complete and reviewed.
- Operations/support/security/documentation handover is complete.
- The Owner explicitly accepts Phase 9.
- Production deployment and operational release are handled as separate decisions.

## 10. Approval and execution boundary

**Current state: DRAFT — awaiting Owner review and approval.**

No Phase 9 implementation branch, migration, code change, permission change, production action or WP1 commencement is authorized by this plan. After approval, the next step is to complete the Phase 9 traceability/source-inventory artifacts and WP1 detailed contract, then stop for explicit authorization to begin WP1.

