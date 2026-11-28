# Phase 9 — WP1 Detailed Implementation Contract
## Platform Admin Foundation, Dashboard and Tenant Discovery

**Status:** Implementation source prepared; local engineering/runtime checks and Owner manual verification pending.  
**Traceability:** Phase_9_Requirement_Traceability_Matrix.md, WP1-01 through WP1-10.  
**Inventory:** Phase_9_Existing_Source_Inventory.md.  
**Authorization:** Owner approved WP1 implementation on 28 November 2026. This contract does not authorize production deployment or WP2.

## 1. Objective and scope

WP1 delivers a distinct, secure Platform Admin Overview and Tenant directory/detail experience.

### Included
1. Verify the existing Admin identity/session/MFA/CSRF/security boundary.
2. Add an Overview/Dashboard with authoritative platform metrics.
3. Replace the unbounded Tenant list with server-paginated, filterable directory data.
4. Add a bounded Tenant detail endpoint and responsive detail UI.
5. Document metric definitions, UTC cutoffs, freshness and unavailable states.
6. Reconcile dashboard/directory results against authoritative fixtures.
7. Remove per-Tenant N+1 queries and full subscription-event expansion from the directory path.

### Excluded
Onboarding mutations and Owner recovery (WP2); subscription mutations/expiry work (WP3); domain mutations (WP4); lifecycle mutations and Device revocation (WP5); support/Audit redesign (WP6); Phase 10 sync transport/provision/cursors/receipts/outbox/conflict/generation recovery; offline Admin; cross-Tenant Shop Sales/Cash/Debt/Stock valuation/Profit/customer analytics; new Admin tables/permissions; payment gateway; new infrastructure or broad architecture redesign.

## 2. Architecture and source owners

| Layer | Source owner | Responsibility |
|---|---|---|
| Routes | app/Config/Routes.php | Explicit GET-only Platform read routes |
| Transport | PlatformOperationsController.php or focused read controller | Validate input, call query service, canonical ApiResponse |
| Query/application | Focused dashboard, directory and detail query services under module-map ownership | Read authoritative tables, aggregate and paginate |
| Existing subscription query | SubscriptionQuery | Single-Tenant detail only when bounded |
| API wrapper | resources/js/api/phase-one.js | Existing apiRequest conventions |
| Navigation | resources/js/shared/workspace-registry.js | Overview and Tenant directory |
| UI | resources/js/admin/app.js and focused components | Dashboard, filters, pagination and detail |
| View | app/Views/admin/index.php | Existing mount and escaped permission attributes |
| Assets | Existing Sass/Node pipeline | Rebuild generated public/assets from resources |

Keep module dependency direction: controller → application query/service → owning data/domain query. No generic repository framework or business logic in Vue/controllers.

## 3. Security and authorization

- New GET routes use platformAdmin:platform.access.
- Existing filter must verify Shield authentication, platform_admin group, platform.access and recent server-established MFA no older than 15 minutes.
- Vue permission visibility is presentation only; backend authorization is authoritative.
- No new permission is introduced.
- Global session security, CSRF, HTTPS and security headers remain active.
- No ShopContext is fabricated; no Tenant membership is created for Platform Admin.
- WP1 endpoints are read-only. No Tenant, Shop, Owner, subscription, domain, Device or Audit mutation and no support scope creation.
- Never return credentials, auth secrets, MFA/recovery tokens, Device verifiers/cookies, internal numeric IDs, raw SQL errors, raw Audit metadata or Shop transaction data.
- Tenant detail is an operational summary, not an unrestricted Tenant data viewer.

### MFA preflight gate

Inspect PlatformMfaService and Shield Email2FA completion integration before exposing additional Admin data. Confirm freshness timestamp is written only after successful factor verification, not on login redirect/step-up page visit. The historical foundation acceptance records this as an engineering finding. If unresolved, stop and obtain approval for the minimal security correction before WP1 release.

## 4. API contract

Use /api/v1/platform, standard ApiResponse envelope and request correlation ID.

### 4.1 Dashboard

**Route:** GET /api/v1/platform/operations/dashboard  
**Filter:** platformAdmin:platform.access

Return:
- Tenant totals by ONBOARDING, ACTIVE, SUSPENDED, CLOSED.
- Shop totals by ACTIVE, INACTIVE.
- Current Trial, current Paid, Expired and Scheduled entitlement Tenant counts.
- Enabled Platform Administrator count.
- Registered, active and revoked Device counts if Device schema is ready.
- Sync-generation/readiness facts supported by current schema.
- Maximum 10 upcoming subscription expiries.
- One captured UTC as_of timestamp and per-source freshness/availability state.

Illustrative response shape only (not actual environment data):
```json
{
  "metrics": {
    "tenants": {"total": 0, "onboarding": 0, "active": 0, "suspended": 0, "closed": 0},
    "shops": {"total": 0, "active": 0, "inactive": 0},
    "entitlements": {"trial_current": 0, "paid_current": 0, "expired": 0, "scheduled": 0},
    "platform_administrators": {"enabled": 0},
    "devices": {"registered": 0, "active": 0, "revoked": 0},
    "sync_foundation": {"active_generation_count": 0, "generation_state": "UNAVAILABLE"}
  },
  "upcoming_expiries": [],
  "as_of": "UTC timestamp",
  "freshness": {"state": "CURRENT", "as_of": "UTC timestamp"}
}
```

#### Metric definitions

| Metric | Definition |
|---|---|
| Tenant counts | Count persisted tenants rows by actual status |
| Shop counts | Count shops by actual status |
| Current Trial | Tenant with current period where entitlement_type=TRIAL |
| Current Paid | Tenant with current period where entitlement_type=PAID |
| Current period | starts_at <= captured UTC as_of AND ends_at > captured UTC as_of |
| Expired | No current period and latest period ended at/before as-of |
| Scheduled | Tenant has a future entitlement period; not current |
| Platform Administrators | Enabled Shield users in platform_admin group; confirm enabled/disabled semantics from pinned Shield schema before implementation |
| Device counts | registered_devices counts by status; if schema unavailable report unavailable, not zero |
| Sync foundation | Active sync_generations count/readiness only; not proof of successful Phase 10 transport or per-device freshness |
| Upcoming expiry | Current entitlement ends within next 30 days, ordered by end timestamp, maximum 10 |

Counts are Tenant counts, not raw period counts. Capture one UTC as-of instant and reuse it across all time-dependent metrics. If multiple current periods exist for one Tenant, surface an integrity/attention state and do not silently pick an arbitrary period. Do not persist mutable subscription status.

**Platform Admin count:** Confirm exact Shield schema and disabled semantics from the installed pinned Shield migration before coding. Count only enabled users in platform_admin. If schema does not support a reliable enabled distinction, stop and request a metric-definition decision rather than assuming all group rows are active.

No dashboard cache or new summary table. If an optional source is unavailable, return typed unavailable state, not a false zero. Core Tenant/Shop query failure returns safe request failure.

### 4.2 Tenant directory

**Route:** GET /api/v1/platform/operations/tenants  
**Filter:** platformAdmin:platform.access

Parameters:
- q: optional trimmed search string, bounded length 1–100 characters.
- tenant_status: ONBOARDING, ACTIVE, SUSPENDED, CLOSED.
- shop_status: ACTIVE, INACTIVE.
- entitlement_status: TRIAL, ACTIVE, EXPIRED, SCHEDULED, NONE.
- page: positive integer, default 1.
- per_page: 1–50, default 25.

Reject malformed or unknown filter values with 422; do not silently ignore. Search only Tenant public UUID/business name, Shop public UUID/name and hostname. Owner email/phone search is excluded unless separately approved. Use bound parameters and allowlisted filters.

Order newest-first by Tenant creation timestamp with a deterministic internal-ID tie-breaker. Prefer bounded keyset pagination if compatible with repository conventions; otherwise stable page-number pagination with metadata. Never load all Tenants and paginate in JavaScript.

Illustrative response:
```json
{
  "items": [{
    "tenant_public_id": "uuid",
    "business_name": "Example business",
    "tenant_status": "ACTIVE",
    "tenant_created_at": "UTC timestamp",
    "shop": {"public_id": "uuid", "name": "Example shop", "status": "ACTIVE", "primary_hostname": "example.dawatrack.com"},
    "entitlement": {"status": "TRIAL", "plan_code": "TRIAL", "starts_at": "UTC timestamp", "ends_at": "UTC timestamp"},
    "active_staff_count": 2
  }],
  "pagination": {"page": 1, "per_page": 25, "total": 0, "total_pages": 0},
  "as_of": "UTC timestamp"
}
```

The example is illustrative only. Omit unavailable optional values; do not fabricate. Use one bounded page query plus fixed aggregate queries/derived tables. No per-Tenant loop. Active Staff is a grouped aggregate. Domain summary selects the authoritative active/primary verified hostname. No challenge/token fields. Directory entitlement is a small summary, not all periods/events. Count and page query filters must match.

### 4.3 Tenant detail

**Route:** GET /api/v1/platform/operations/tenants/{tenantPublicId}  
**Filter:** platformAdmin:platform.access

Resolve only by public UUID. Return:
- Tenant identity, status and lifecycle timestamps/reason.
- One-Shop identity/status/contact/location summary.
- Primary Owner display name and safe account identifier only if data policy permits.
- Current and next entitlement, bounded previous-period summary and active Staff count.
- Primary domain, verification and certificate state.
- Device counts if schema ready.
- Sync foundation facts supported by existing schema.
- Onboarding milestones only where authoritative persisted evidence exists.

No Sales, Cash, Debt, Customer, Stock valuation, Profit, private transaction or raw Audit payload. Unknown/malformed UUID returns safe 404 without internal ID disclosure.

### 4.4 Existing routes

Preserve onboarding, domain actions, subscription mutation, lifecycle mutation, Platform Audit and support Audit contracts. Frontend may replace/extend its platformOperations() wrapper, but no unrelated endpoint contract is silently changed.

## 5. Backend/query design

Create focused read-only query services, final namespace aligned with module-map ownership:
- PlatformDashboardQuery
- PlatformTenantDirectoryQuery
- PlatformTenantDetailQuery

A small coordinating application service is acceptable if it matches repository convention. No general query framework.

Controller responsibilities: parse/validate GET inputs and public UUID; call query service; return canonical response; log request ID and safe exception class only. No business rules, manual Audit creation or per-record query loops.

Inspect actual MySQL 8.4 schema/indexes before SQL. Avoid multiplicative joins across multiple one-to-many tables. Use derived aggregates, correlated subqueries or a small fixed set of aggregate queries over bounded Tenant IDs. Use one UTC as-of instant. Do not call SubscriptionQuery::forTenant() once per Tenant. Directory must not retrieve full subscription event history. If query plans prove an index missing, record EXPLAIN evidence and propose exact additive migration/SQL/register/rollback for Owner approval before implementation.

The current PlatformOperationsController::tenants() must stop loading all Tenants, looking up Tenant IDs per row, querying full subscription history per Tenant, counting Staff per Tenant and returning the whole dataset for browser-side search.

## 6. Frontend contract

### Navigation
Extend adminPages with Overview and Tenant directory. Preserve Add shop, Plans, Web addresses and Activity. Existing Shops workspace may be relabeled Tenants while retaining hash compatibility if practical.

### Overview
Show Tenant status counts; entitlement counts; Shop status counts; enabled Platform Administrators; registered/active/revoked Devices; sync foundation status; up to 10 upcoming expiries. Explain that Expired entitlement is distinct from Suspended/Closed Tenant status. No financial or Shop transaction analytics.

### Directory
Server-side search/filter; pagination metadata; Tenant and Shop names/status; entitlement summary; Staff count; verified primary hostname; open bounded detail by public UUID. Existing Plans/lifecycle actions remain in their existing authorized workspace; detail is read-only.

### Required UI states
Loading, refresh, empty platform, no filter matches, validation error, permission/MFA expiry, offline, partial metric unavailable, freshness/as-of, detail not found and safe retry. Ignore stale responses from earlier filter/page requests.

### Responsive/accessibility
Phone: stacked metrics/cards and accessible filters without horizontal overflow. Tablet: readable two-column layout. Desktop: efficient summary grid/list. Visible keyboard focus, semantic headings/labels, text status not color alone. Reuse existing DawaTrack UI components/tokens; no wholesale redesign.

### Data handling
No localStorage/sessionStorage/IndexedDB persistence of Admin API data. No offline cache/service-worker caching of authenticated Admin responses. No private Tenant detail in query strings. Render server data as text; no v-html.

## 7. Data/schema contract

Reuse tenants, shops, tenant_memberships, Shield users/group tables, subscriptions, subscription_plans, subscription_events, shop_domains, registered_devices when ready, sync_generations and existing Phase 2/7 Sync records.

No new table or schema change planned. If measurement proves a new index necessary, document query and EXPLAIN evidence, selectivity/write impact, exact additive CI4 migration and paired SQL, schema register and rollback; obtain Owner approval first. Never edit historical migrations or change schema v1.1 semantics.

## 8. Error semantics

| Condition | Expected outcome |
|---|---|
| Unauthenticated | Existing 401 |
| Non-platform/missing platform.access | Existing 403 |
| Recent MFA expired | Existing 403 MFA_REQUIRED and step-up |
| Invalid filters/page/per_page | 422 with field details |
| Unknown Tenant UUID | Safe 404 |
| Device schema unavailable | Partial unavailable metric, not zero |
| Core directory/dashboard query failure | Safe 500 with correlation ID, no SQL detail |
| Offline | UI offline state; no fake cached response |

No automatic retry that may hide stale state; user-triggered reload is acceptable.

## 9. Manual verification register

Record actual PASS/FAIL/BLOCKED, environment, timestamp, actor, fixture, steps, expected/observed result, evidence and reviewer. Do not mark PASS before manual execution.

| Case | Scenario | Expected result |
|---|---|---|
| WP1-M01 | Unauthenticated dashboard | 401; no data |
| WP1-M02 | Authenticated non-platform user | 403 |
| WP1-M03 | Platform Admin missing platform.access | 403 |
| WP1-M04 | Expired MFA | 403 MFA_REQUIRED |
| WP1-M05 | Login/step-up redirect without factor completion | Does not refresh MFA timestamp |
| WP1-M06 | Valid fresh Admin dashboard | 200, real metrics and UTC as-of |
| WP1-M07 | Reconcile Tenant status counts | Exact match |
| WP1-M08 | Trial/Paid/Expired/Scheduled boundary dates | Half-open UTC semantics |
| WP1-M09 | Tenant with current and future period | Current and scheduled classified correctly |
| WP1-M10 | Tenant with only ended period | Expired, no mutation |
| WP1-M11 | Multiple current periods | Integrity attention; no arbitrary double count |
| WP1-M12 | Empty platform | Zero counts and empty state |
| WP1-M13 | Device schema unavailable | Unavailable, not zero |
| WP1-M14 | Active generation count not one | Attention/unavailable, not READY |
| WP1-M15 | More than 10 upcoming expiries | Maximum 10, correctly ordered |
| WP1-M16 | Default directory request | At most 25 rows and metadata |
| WP1-M17 | Invalid page/per_page incl >50 | 422 |
| WP1-M18 | Each valid Tenant status filter | Matching rows only |
| WP1-M19 | Combined Shop/entitlement filters | Correct intersection |
| WP1-M20 | Search by allowed identifiers/names/hostname | Server-side correct matches |
| WP1-M21 | Overlong/malformed search | Safe 422; no injection |
| WP1-M22 | Large fixture across pages | Stable pages; no duplicate/omitted rows under stable data |
| WP1-M23 | Directory total vs fixture | Exact match |
| WP1-M24 | Valid Tenant detail | Safe bounded summary |
| WP1-M25 | Unknown/malformed UUID | Safe 404 |
| WP1-M26 | Tenant without domain/Device records | Explicit empty/unavailable values |
| WP1-M27 | Inspect all response DTOs | No secrets, internal IDs, transactions or raw Audit metadata |
| WP1-M28 | Page of 25 query count | Fixed bounded query count; no N+1 |
| WP1-M29 | Query plan | Appropriate existing indexes; no unbounded expansion |
| WP1-M30 | Phone layout | No overflow; readable status |
| WP1-M31 | Tablet/desktop layout | Responsive and usable |
| WP1-M32 | Offline Admin | Offline state; no cached Admin data |
| WP1-M33 | Rapid filter/page changes | Stale response cannot overwrite latest |
| WP1-M34 | Activity support target selection | Bounded target selection; support contract unchanged |
| WP1-M35 | Existing Add shop/Plans/Domains/Activity | Existing workflows remain intact |
| WP1-M36 | Cross-Tenant/Shop UUID manipulation | No authority escalation/enumeration |
| WP1-M37 | Forbidden financial/operational aggregates | Absent from API/UI |
| WP1-M38 | Browser storage/cache inspection | No persisted Admin data |

### Supporting engineering checks
PHP syntax; existing Node build and generated asset parity; route/filter and DTO inspection; parameter binding/allowlists; MySQL EXPLAIN; schema diff; pinned Shield schema review. No automated test framework/suite is introduced. Engineering checks support but do not replace manual acceptance.

## 10. Implementation sequence

1. Reopen current main and approved contract; confirm no intervening changes.
2. Inspect PlatformMfaService and Shield factor-completion integration.
3. Confirm Shield group/user schema and enabled-user metric semantics.
4. Finalize DTOs and response contracts.
5. Implement read-only query services without schema change.
6. Add explicit GET routes and thin controller actions using platform.access.
7. Replace unbounded/N+1 directory query with bounded server-side search/pagination.
8. Add Overview, directory and detail UI using existing components.
9. Update API wrappers and rebuild generated assets.
10. Run engineering checks and prepare evidence register.
11. Present changed files, query evidence, security review, limitations and exact commit/PR.
12. Stop for Owner's local manual verification and explicit WP1 acceptance. Do not start WP2 before acceptance.

## 11. WP1 Definition of Done

- Existing Admin identity, session, platform group/permission and successful-factor MFA freshness verified.
- Dashboard uses real authoritative data, documented definitions and one UTC as-of.
- No cross-Tenant Shop financial/operational analytics.
- Directory is server-filtered, server-paginated, bounded and free of N+1 expansion.
- Tenant detail is safe, bounded and public-ID based.
- No duplicate tables, new permission, unapproved schema change or infrastructure.
- Device/sync metrics honestly reflect readiness and Phase 10 boundary.
- Existing Add shop, Plans, Domains, Activity and support Audit remain intact.
- All manual cases recorded with actual results and Owner review.
- No unresolved critical/high security or integrity defect.
- Owner explicitly accepts WP1.

## 12. Authorization boundary

**WP1 implementation source has been prepared.** The next gate is local PHP syntax/build verification and Owner manual verification using the WP1 implementation report. No production deployment has been performed. WP2 remains blocked until explicit WP1 acceptance.
