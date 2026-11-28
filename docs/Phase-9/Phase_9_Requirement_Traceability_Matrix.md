# Phase 9 — Requirement Traceability Matrix

**Status:** Phase 9 plan approved; WP1 Owner-accepted; WP2 implementation in progress.  
**Scope:** Planning and implementation traceability on the Phase 9 feature branches; production deployment is not authorized.  
**Baseline:** main at Phase 8 closure commit 2972f80bbc0d94c6a9e7c8e5b5756e378d4039e6.

## Reading this matrix

The governing source defines the requirement; existing paths record inspected source, not proof of runtime behavior. Disposition is Reuse/verify, Extend, Missing, or Out of scope. Update status only from observed evidence. A path is an implementation owner, not permission to bypass module boundaries.

## Requirement matrix

| ID | Requirement | Governing source | WP | Existing source | Disposition | Acceptance evidence |
|---|---|---|---|---|---|---|
| P9-01 | Separate online-only Platform Admin interface | Technical Blueprint §10; Roadmap Phase 9 | WP1 | AdminAuthController, admin view, resources/js/admin/app.js, workspace-registry.js | Reuse/extend | Separate shell/session; no Shop authority leakage; responsive checks |
| P9-02 | Shield platform identity, permission and recent MFA on every protected request | Technical Blueprint; security foundation | WP1, WP7 | PlatformAdminFilter, PlatformAdminIdentityFilter, PlatformMfaService, AuthGroups.php, Routes.php | Reuse/verify; historical MFA concern needs proof | Anonymous/non-platform/missing-permission/stale-MFA denied; successful factor required |
| P9-03 | Platform dashboard with Tenant, Shop, entitlement, suspended Tenant, platform Admin, Device and sync-foundation metrics | Technical Blueprint §10.2; Roadmap Phase 9 | WP1 | No dedicated dashboard endpoint/page found; underlying tables exist | Missing | Reconcile metrics against authoritative fixtures and definitions |
| P9-04 | Dashboard excludes Shop Sales, Cash, Debt, Stock valuation and Profit analytics | Technical Blueprint §10.2 | WP1, WP7 | No dashboard exists | Required negative boundary | API payload/UI inspection; forbidden aggregates absent |
| P9-05 | Tenant search, filters, bounded pagination and detail | Roadmap Phase 9; Technical Blueprint §10.4 | WP1 | GET platform/operations/tenants; PlatformOperationsController::tenants(); Admin Shops workspace | Extend; current endpoint unbounded/N+1 | Server-side search/filter/page; stable order; bounded query count |
| P9-06 | Shop registration and onboarding progress | Technical Blueprint §10.3; Phase 1 onboarding | WP2 | OnboardingService, OnboardingController, POST platform/tenants, Admin onboarding form | Reuse/verify; progress UI may need extension | Atomic one-Shop graph, Owner, Trial, branding/domain, Audit and change publication |
| P9-07 | Primary Owner creation during onboarding | Technical Blueprint §10.3; Database Design §14 | WP2 | OnboardingService::onboard()/createOwner() | Reuse/verify | One active Owner; identity and forced reset behavior; no secret leakage |
| P9-08 | Primary Owner recovery and reassignment | Roadmap Phase 9; Technical Blueprint §10.4 | WP2 | No Platform Owner recovery/reassignment service/route found in inspected Identity/TenantShop surface; StaffService is Staff-only | Missing | Serialized invariant, old session invalidation, recovery, Audit and rollback |
| P9-09 | Activation, renewal, upgrade, downgrade and Trial extension | Database Design §13; Phase 1 subscription ops | WP3 | SubscriptionService, SubscriptionQuery, Platform operations API and Admin Plans | Reuse/verify | Immutable half-open periods/events, reason, Audit, change publication, overlap and Staff-cap refusal |
| P9-10 | Expiry recording and visibility | Phase 1 subscription ops; Roadmap Phase 9 | WP3 | RecordSubscriptionExpiries Spark command; SubscriptionQuery | Reuse/verify | Repeat-safe expiry event; no entitlement mutation |
| P9-11 | Tenant/Shop suspend, reactivate and close | Roadmap Phase 9; Phase 1 lifecycle ops | WP5 | PlatformLifecycleService, Platform operations routes/UI | Reuse/verify | Valid transitions; CLOSED terminal; AccessGate and cross-host effects; atomic evidence |
| P9-12 | Branding/domain inventory and lifecycle | Roadmap Phase 9; Technical Blueprint; Database Design §14 | WP4 | BrandingService, DomainService, BrandResolver, HostnamePolicy, PlatformDomainController | Reuse/verify | Ownership challenge, TLS, activation, primary/fallback; hostname never grants authority |
| P9-13 | Platform Device inventory/revocation | Roadmap Phase 9; Phase 7 Device ops | WP5 | DeviceService, DeviceQuery, DeviceController and Shop UI; no Platform Device route/UI found | Extend | Bounded safe inventory; reasoned revoke; proof invalidation; history retained |
| P9-14 | Sync/readiness diagnostic entry point | Roadmap Phase 9; Technical Blueprint; Phase 7 Device ops | WP1/WP5 | sync_generations, Phase 2 change publication, Phase 7 Device acknowledgement foundation | Partial | Show only supported evidence; no invented sync-health claims; Phase 10 excluded |
| P9-15 | Explicit reasoned read-only support context | Technical Blueprint §10.5; security foundation; Phase 2 Audit ops | WP6 | SupportAuditAccessService, AuditController support routes, AuditPanel | Reuse/verify | Explicit target/reason, same-Admin 15-minute continuation, evidence before results, no impersonation |
| P9-16 | Platform Audit list/detail | Technical Blueprint §10.4; Phase 2 Audit ops | WP6 | AuditQueryService, AuditQuery, AuditController and AuditPanel | Reuse/verify | Platform-only scope, bounded cursor, redacted DTOs, non-enumerating detail |
| P9-17 | Audit every sensitive Admin mutation atomically | Roadmap Phase 1 Audit requirements; Audit contract | WP2–WP7 | AuditService and domain services | Reuse/verify per mutation | Failure rollback leaves no partial business/Audit/change state |
| P9-18 | Dashboard/directory query bounds and performance | Roadmap Phase 9; Database Design | WP1, WP7 | Current Tenant endpoint loads all rows and performs per-Tenant ID/subscription/staff queries | Defect to correct | Max page size; no N+1; reviewed EXPLAIN/query counts |
| P9-19 | Responsive Admin actions on phone/tablet/desktop | Roadmap Phase 9 responsive requirements | WP1–WP7 | Existing Admin workspace and shared UI components | Extend/verify | Manual critical-flow checks on all form factors |
| P9-20 | Online-only Admin; no authenticated offline cache | Technical Blueprint §10; Roadmap Phase 9 | WP1, WP7 | Admin uses API and navigator.onLine | Preserve/verify | Offline requests fail safely; no Admin data persisted for offline |
| P9-21 | No duplicate Admin business tables | Database Design §14; module map | All | Existing authoritative Tenant/Shop/identity/subscription/domain/device/Audit tables | Hard constraint | Schema diff and code review |
| P9-22 | Platform Admin has no synthetic Tenant membership or Shop operating authority | Technical Blueprint §§2.6, 10; security foundation | All | Shield platform group; Audit actor separation | Hard constraint | Cross-boundary negative tests |
| P9-23 | No silent impersonation | Technical Blueprint §10.5; Phase 2 support ops | WP6, WP7 | Support Audit scope, not impersonation | Hard constraint | No impersonated identity/session or hidden membership |
| P9-24 | Phase 10 transport/provision/cursor/receipt/outbox/conflict/generation recovery excluded | Roadmap Phase 9/10; Phase 7 Device ops | WP5, WP7 | Phase 7 readiness foundation only | Out of scope | No Phase 10 routes/schema/claims introduced |
| P9-25 | Complete auditable SaaS console and support procedures | Roadmap Phase 9 deliverables/DoD | WP7 | Phase 1/2/7 docs and Admin references | Missing as consolidated handover | Owner-approved runbooks, evidence register, journeys and closure record |

## WP1 traceability detail

| ID | Requirement | Source owner | Acceptance evidence |
|---|---|---|---|
| WP1-01 | Separate Admin shell and MFA boundary | AdminAuthController, PlatformAdminFilter, Admin view/app | Session/route/MFA tests |
| WP1-02 | Dashboard aggregates from authoritative tables | New dashboard query/service and GET controller action | Fixture reconciliation |
| WP1-03 | Metric definitions and UTC cutoffs documented | Query contract/docs | Independent recalculation |
| WP1-04 | Server-paginated and filterable Tenant directory | PlatformOperationsController + directory query | Page/filter/boundary checks |
| WP1-05 | Bounded safe Tenant detail | Detail query/service + Admin detail screen | Valid/unknown UUID checks |
| WP1-06 | No N+1 or full subscription event expansion | Query service | Query count/EXPLAIN/large fixture |
| WP1-07 | Loading/empty/error/offline/stale states | Admin UI | Browser manual checks |
| WP1-08 | No cross-Tenant financial analytics | DTO allowlist | Negative payload/UI inspection |
| WP1-09 | No new permission/schema without approval | Existing platform.access and schema | Auth/schema diff |
| WP1-10 | Responsive dashboard/directory | Admin UI | Phone/tablet/desktop checks |

## Status maintenance

Allowed statuses: NOT STARTED, IN PROGRESS, BLOCKED, READY FOR MANUAL VERIFICATION, PASS, FAIL, DEFERRED BY APPROVED SCOPE CHANGE. Record actual commit/path, case ID, evidence, observed result and reviewer. Code existence, build success or static assertions alone never constitute PASS. Scope changes require explicit Owner approval.


## WP1 implementation status — 28 November 2026

**Overall status: PASS — the Owner reports completing manual verification and explicitly accepted WP1 on 28 November 2026. Individual case-level observations were not included in the acceptance message.**

| WP1 ID | Implementation source | Current status |
|---|---|---|
| WP1-01 | AdminAuthController, PlatformAdminFilter, PlatformMfaService, PlatformAdminEmail2FA, Admin shell | IN PROGRESS — source boundary inspected; runtime MFA/session checks pending |
| WP1-02 | Modules/Platform/Application/PlatformDashboardQuery.php; GET operations/dashboard | IN PROGRESS — source prepared; database reconciliation pending |
| WP1-03 | Dashboard query uses one UTC as_of; implementation report documents metric rules | IN PROGRESS — independent fixture reconciliation pending |
| WP1-04 | Modules/Platform/Application/PlatformTenantDirectoryQuery.php; GET operations/tenants | IN PROGRESS — bounded server-side query prepared; filter/pagination runtime checks pending |
| WP1-05 | Modules/Platform/Application/PlatformTenantDetailQuery.php; GET operations/tenants/{publicId} | IN PROGRESS — bounded safe detail prepared; local API checks pending |
| WP1-06 | Grouped Staff and entitlement projections; no per-Tenant query loop | IN PROGRESS — query-count/EXPLAIN evidence pending |
| WP1-07 | resources/js/admin/app.js | IN PROGRESS — loading/empty/error/offline/detail states prepared; browser checks pending |
| WP1-08 | Dashboard DTO and UI allowlist | IN PROGRESS — source inspection completed; response payload review pending |
| WP1-09 | Existing platform.access permission and current schema reused | IN PROGRESS — source diff review completed; local schema diff pending |
| WP1-10 | Existing Bootstrap/UI responsive layout | IN PROGRESS — source prepared; phone/tablet/desktop checks pending |

The implementation report is `WP1_Implementation_Report_and_Local_Verification.md`. Owner acceptance closes the WP1 gate and authorizes WP2. Preserve any local WP1 case evidence separately; the assistant does not invent case-level observations.


## WP2 implementation status — 28 November 2026

**Overall status: IN PROGRESS — implementation source being prepared on `phase-9/wp2-tenant-onboarding-primary-owner`. Local PHP/MySQL runtime verification and Owner manual acceptance are pending.**

| WP2 ID | Requirement | Implementation source | Current status |
|---|---|---|---|
| WP2-01 | Guided Tenant/Shop onboarding without Admin-managed passwords | TenantShop OnboardingService; Admin onboarding form | IN PROGRESS — invitation-first graph implemented; runtime checks pending |
| WP2-02 | Primary Owner identity creation or safe reuse | OnboardingService; MembershipInvariantService | IN PROGRESS — email identity, active-account and membership invariants implemented |
| WP2-03 | One-time Owner invitation and secure acceptance | OwnerInvitationService; Shield magic-link; OwnerActivationController | IN PROGRESS — mail delivery and end-to-end acceptance pending |
| WP2-04 | Pending/active onboarding states and Trial timing | TenantShop OnboardingService | IN PROGRESS — pending Tenant/Shop/Owner; Trial begins at activation |
| WP2-05 | Owner recovery and invitation resend | OwnerInvitationService; Platform Operations API/UI | IN PROGRESS — delivery/retry tests pending |
| WP2-06 | Primary Owner reassignment | OwnerLifecycleService | IN PROGRESS — serialized transaction, membership/grant changes, Audit and session revocation implemented |
| WP2-07 | Former and new Owner session/credential invalidation | OwnerLifecycleService; UserSessionRevocationService | IN PROGRESS — runtime session/remember-token checks pending |
| WP2-08 | Audit and sync atomicity | AuditService; ChangeSequenceService | IN PROGRESS — source transactions prepared; rollback/sequence tests pending |
| WP2-09 | Resumable onboarding and duplicate prevention | OnboardingService; invitation resend | IN PROGRESS — retry path prepared; duplicate/concurrency fixtures pending |
| WP2-10 | Responsive Owner lifecycle UI | Admin app and Owner activation view | IN PROGRESS — browser/manual checks pending |

The WP2 governing contract is `WP2_Tenant_Onboarding_and_Primary_Owner_Lifecycle_Contract.md`. The implementation report and manual verification register will be added before PR review. No WP2 requirement is marked PASS until local runtime and Owner acceptance are recorded.
