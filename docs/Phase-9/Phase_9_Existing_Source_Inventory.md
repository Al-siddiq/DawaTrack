# Phase 9 — Existing Source-Code Inventory

**Status:** Initial inventory completed from source inspection.  
**Baseline:** main at 2972f80bbc0d94c6a9e7c8e5b5756e378d4039e6.  
**Limitation:** This is not a runtime audit. No local XAMPP browser/database fixture or production environment was exercised during this inventory.

## 1. Executive findings

1. The separate Platform Admin shell, Shield platform group, permission-aware navigation, recent-MFA filter and native login/step-up screens already exist.
2. Current Admin navigation is Shops, Add shop, Plans, Web addresses and Activity. No dedicated Overview/Dashboard exists.
3. Tenant onboarding, subscription operations, Tenant/Shop lifecycle, domain operations and Platform Audit/support Audit have existing services, routes and UI.
4. Current Tenant operations endpoint loads every Tenant and then performs per-Tenant Tenant ID lookup, full SubscriptionQuery and active Staff count. Frontend search is in-memory and the complete dataset is reused by Shops, Plans and Activity.
5. No dedicated dashboard aggregate endpoint or Tenant detail endpoint was found.
6. No Platform Primary Owner recovery/reassignment service or route was found in the inspected Identity/TenantShop paths. StaffService is not a substitute.
7. No Platform Device inventory/revocation route or UI was found. Phase 7 Device service/query is Shop-context/Primary-Owner scoped.
8. Platform Audit and support Audit APIs/services exist. Support is an explicit reasoned Audit diagnostic scope, not general Shop impersonation.
9. WP1 should complete the platform read side and fix the unbounded directory rather than rebuild lifecycle foundations.

## 2. Admin identity, shell and route sources

| Source | Observed responsibility | Status |
|---|---|---|
| app/Controllers/Web/AdminAuthController.php | Shield login transition, MFA view/restart | Exists |
| app/Views/admin/index.php | Admin mount element and escaped server permission list | Exists |
| resources/js/admin/app.js | Onboarding, Shops, Plans, Domains, lifecycle and Audit UI | Exists; no dashboard |
| resources/js/shared/workspace-registry.js | Admin navigation | Exists; five pages |
| app/Filters/PlatformAdminFilter.php | Platform group, route permission and recent MFA | Exists; verify successful-factor semantics |
| app/Filters/PlatformAdminIdentityFilter.php | Admin MFA identity boundary | Exists |
| app/Security/PlatformMfaService.php | Server-side recent MFA | Exists; historical foundation concern needs focused review |
| app/Config/AuthGroups.php | Platform group and permission definitions | Exists |
| app/Config/Filters.php | Session security, CSRF, HTTPS, security headers, sensitive throttle | Exists |
| app/Config/Routes.php | Explicit Admin shell and Platform API routes | Exists; AutoRoute disabled |

Current platform permissions: platform.access, platform.onboard, platform.domains, platform.subscriptions, platform.tenants, platform.audit, platform.audit_support. They are assigned to platform_admin. Do not introduce a new WP1 permission.

## 3. Current Platform route inventory

| Route | Method | Handler / permission | Current usage |
|---|---|---|---|
| /platform-admin/login | GET | AdminAuthController | Login transition |
| /platform-admin/mfa | GET | platformAdminIdentity | MFA step-up |
| /platform-admin/mfa/restart | POST | platformAdminIdentity + CSRF | Restart factor |
| /platform-admin | GET | platform.access | Admin shell |
| /api/v1/platform/tenants | POST | OnboardingController / platform.onboard | Add shop |
| /api/v1/platform/domains | GET | PlatformDomainController / platform.domains | Domain inventory |
| /api/v1/platform/domains/{id}/{action} | POST | PlatformDomainController / platform.domains | TLS/activate/primary/deactivate/fallback |
| /api/v1/platform/operations/tenants | GET | PlatformOperationsController / platform.access | Shops, Plans and Activity support targets |
| /api/v1/platform/operations/tenants/{id}/subscriptions | POST | PlatformOperationsController / platform.subscriptions | Plan mutation |
| /api/v1/platform/operations/tenants/{id}/{action} | POST | PlatformOperationsController / platform.tenants | Tenant lifecycle |
| /api/v1/platform/operations/shops/{id}/{action} | POST | PlatformOperationsController / platform.tenants | Shop lifecycle |
| /api/v1/platform/audit-events | GET | AuditController / platform.audit | Platform Audit |
| /api/v1/platform/audit-events/{id} | GET | AuditController / platform.audit | Audit detail |
| /api/v1/platform/audit-events/support/query | POST | AuditController / platform.audit_support | Scoped support Audit |
| /api/v1/platform/audit-events/support/events/{id} | POST | AuditController / platform.audit_support | Support Audit detail |

No dashboard, paginated Tenant directory, Tenant detail, Platform Owner recovery/reassignment or Platform Device administration route was found.

## 4. Existing domain services

| Source | Capability | Phase 9 disposition |
|---|---|---|
| TenantShop/Application/OnboardingService.php | Transactional one-Shop Tenant, Owner identity/membership, branding, platform domain, Trial, Audit and change publication | Reuse/verify WP2 |
| TenantShop/Application/PlatformLifecycleService.php | Tenant/Shop lifecycle with locks, reason, Audit and change publication | Reuse/verify WP5 |
| Subscription/Application/SubscriptionService.php | ACTIVATE/RENEW/UPGRADE/DOWNGRADE/EXTEND; immutable period/event/Audit/change publication and locks | Reuse/verify WP3 |
| Subscription/Application/SubscriptionQuery.php | Current/historical/future entitlement and events for one Tenant | Reuse for single detail only; not bulk directory |
| BrandingDomain/Application/BrandingService.php | Branding/assets | Reuse WP4 |
| BrandingDomain/Application/DomainService.php | Platform domain TLS/activate/primary/deactivate/fallback | Reuse WP4 |
| BrandingDomain/Application/BrandResolver.php, HostnamePolicy.php | Host resolution and hostname rules | Reuse; host never grants authority |
| Device/Application/DeviceService.php | Shop Owner Device enrollment/rename/revoke/recovery/validation | Reuse domain rules; not Platform-wide query |
| Device/Infrastructure/DeviceQuery.php | Shop-scoped Owner history, proof authentication and safe DTO | Do not fabricate ShopContext to reuse for Platform |
| Audit/Application/AuditService.php | Typed append-only Audit | Reuse |
| Audit/Application/AuditQueryService.php | Owner, platform-only and support-scoped Audit pages/details | Reuse |
| Audit/Application/SupportAuditAccessService.php | Reasoned support Audit evidence and 15-minute same-Admin continuation | Reuse; preserve boundary |
| Identity/Application/StaffService.php | Shop Owner Staff create/status/reset/permissions | Not Primary Owner recovery/reassignment |
| Identity/Application/MembershipInvariantService.php | Prevents ordinary identity from active memberships in multiple Tenants | Reuse as constraint; Owner service needs explicit invariant design |

## 5. Current Admin frontend

| Source | Current function | WP1 gap |
|---|---|---|
| resources/js/admin/app.js | Loads domains and platformOperations; renders onboarding, Tenant cards, plan forms, lifecycle, domain cards and AuditPanel | No dashboard, no dedicated detail, full dataset loaded |
| resources/js/shared/workspace-registry.js | Five Admin pages | No Overview or Tenant Detail |
| resources/js/api/phase-one.js | Existing onboarding/domain/lifecycle/subscription/Audit wrappers | No dashboard, paginated directory or detail wrapper |
| resources/js/components/audit-panel.js | Existing Platform and support Audit UI | Reuse; do not duplicate |
| app/Views/admin/index.php | Admin mount and permission data | Reuse |
| public/assets/js/admin/app.js | Generated Admin bundle | Regenerate from resources; never hand-edit |

Current shops, plans and activity workspaces load the same full Tenant operations list. Search is performed in the browser. Activity uses the list for support target selection. Domain inventory is a separate endpoint.

## 6. Critical directory performance defect

PlatformOperationsController::tenants() currently:
1. selects all Tenants and Shop fields without a page limit;
2. looks up each Tenant's internal ID again by public ID;
3. invokes SubscriptionQuery::forTenant() per Tenant, loading all periods and all subscription events;
4. counts active Staff separately for every Tenant;
5. returns the expanded list for client-side search.

This is unbounded and N+1. WP1 must replace it with bounded server-side directory queries and a separate Tenant detail query. A client-side pagination control over the existing full dataset is not an acceptable fix.

## 7. Authoritative data sources

| Data | Table/source | WP1 use |
|---|---|---|
| Tenant identity/status/timestamps | tenants | Counts, directory and detail |
| Shop identity/status/contact/location | shops | Directory/detail |
| Owner role/membership | tenant_memberships | Safe detail summary |
| Platform users/groups | Shield users and group tables | Enabled Platform Admin metric, after schema confirmation |
| Entitlement periods/plans/events | subscriptions, subscription_plans, subscription_events | Derived dashboard and detail summaries |
| Domain verification/TLS/primary | shop_domains | Safe domain summary |
| Device state | registered_devices | Counts if schema ready |
| Sync generation/change/readiness | sync_generations and existing Phase 2/7 Sync records | Only supported readiness facts |
| Audit | audit_events | Existing Activity/support workflows |

No dashboard/directory persistence table is authorized or required.

## 8. Findings and required handling

| Finding | Consequence | Handling |
|---|---|---|
| No dashboard endpoint/UI | WP1 gap | Add focused read-only query/service/controller action and Overview UI |
| Unbounded Tenant endpoint/N+1 | Scalability and data over-fetch | Bounded server-side query; remove per-Tenant lookups |
| No Tenant detail endpoint/UI | WP1 gap | Add safe public-ID detail endpoint and view |
| Owner recovery/reassignment absent from Platform surface | WP2 gap | Identity-owned service; do not repurpose StaffService |
| Platform Device administration absent | WP5 gap | Explicit Platform policy/query after authority design |
| Shield enabled-user metric definition not verified | Metric ambiguity | Inspect pinned Shield schema; do not guess enabled semantics |
| Sync health beyond generation/readiness depends on later work | Scope risk | Report only existing authoritative facts; Phase 10 excluded |
| Historical Admin MFA concern | Security risk | Verify factor completion sets freshness; redirect alone must not |
| Support Audit is not general support access | Boundary | Preserve explicit target/reason/15-minute same-Admin scope |

## 9. Inventory boundary

This inventory supports WP1 contract preparation. It does not prove runtime behavior, database migration state, production performance or manual acceptance. No source code, schema, permission, route or generated asset was changed by this inventory.
