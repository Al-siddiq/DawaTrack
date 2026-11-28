# Phase 9 — WP1 Implementation Report and Local Verification

**Work package:** WP1 — Platform Admin Foundation, Dashboard and Tenant Discovery  
**Status:** Implementation source prepared; awaiting local engineering/runtime checks and Owner manual verification.  
**Authorization:** Owner approved and authorized WP1 on 28 November 2026.  
**Branch:** `docs/phase-9-strategic-implementation-plan`  
**Runtime acceptance:** NOT YET RUN.  
**Production deployment:** Not performed.

## 1. Delivered implementation

### Backend read models

New read-only Platform query services:
- `app/Modules/Platform/Application/PlatformDashboardQuery.php`
- `app/Modules/Platform/Application/PlatformTenantDirectoryQuery.php`
- `app/Modules/Platform/Application/PlatformTenantDetailQuery.php`

The new Platform module is documented in `docs/modules/module-map.md`. It owns cross-module Platform Admin read projections only. It does not own Tenant business mutations, ShopContext, membership or a parallel data store.

### Transport and routes

Updated:
- `app/Controllers/Api/PlatformOperationsController.php`
- `app/Config/Routes.php`

Added protected GET routes:
- `GET /api/v1/platform/operations/dashboard`
- `GET /api/v1/platform/operations/tenants`
- `GET /api/v1/platform/operations/tenants/{tenantPublicId}`

All three use the existing `platformAdmin:platform.access` filter. Existing subscription and lifecycle POST routes remain on their existing permissions and services.

### Dashboard metrics

Dashboard reads the authoritative Tenant, Shop, subscription, Shield, Device and Sync-generation records. It returns:
- Tenant counts by ONBOARDING, ACTIVE, SUSPENDED and CLOSED.
- Shop counts by ACTIVE and INACTIVE.
- Current Trial, current Paid, Expired and Scheduled entitlement Tenant counts.
- Multiple-current-period integrity count.
- Enabled Platform Administrator count using the pinned Shield 1.4.1 `users.active`, `users.deleted_at` and `auth_groups_users.group` schema.
- Device counts where the expected Device schema fields exist; otherwise the Device metric is explicitly UNAVAILABLE.
- Active Sync-generation count and generation readiness only. This is not a Phase 10 sync-health claim.
- At most ten current entitlement expiries within the next 30 days.
- One captured UTC `as_of` timestamp used for all time-dependent calculations.

Entitlement counts are Tenant counts, not raw subscription-period counts. A Tenant with more than one current period is flagged for integrity attention rather than counted as Trial or Paid. Expired entitlement is not treated as automatic Tenant suspension.

### Tenant directory

The directory now:
- Validates search/filter/page parameters.
- Supports Tenant status, Shop status and entitlement filters.
- Searches Tenant public UUID/business name, Shop public UUID/name and active primary verified hostname.
- Enforces a default page size of 25 and a maximum of 50.
- Uses deterministic newest-first ordering.
- Returns only safe summary fields and public identifiers.
- Uses grouped Staff aggregation and a grouped subscription projection.
- Does not return subscription event history.
- Does not expose the internal numeric Tenant sort key.
- Avoids per-Tenant ID, subscription-history and Staff-count query loops.

The former unbounded `PlatformOperationsController::tenants()` implementation has been removed.

### Tenant detail

The detail endpoint resolves only by Tenant public UUID and returns a bounded operational summary:
- Tenant status and lifecycle timestamps/reason.
- Shop identity, status, contact and location.
- Primary Owner membership state without exposing credentials or recovery material.
- Active Staff count.
- Current entitlement, next scheduled entitlement and up to three recent historical periods.
- Multiple-current-period integrity warning.
- Active primary domain status.
- Device counts when the Device schema is ready.
- Sync-generation readiness facts.

It does not return Shop Sales, Cash, Debt, Customer records, Stock valuation, Profit, private transaction records, raw Audit payloads, credentials, Device verifiers or internal numeric IDs.

### Frontend

Updated source:
- `resources/js/admin/app.js`
- `resources/js/api/phase-one.js`
- `resources/js/shared/workspace-registry.js`

Added Overview and renamed the existing Shops navigation item to Tenants while retaining its existing page ID/hash. Added responsive dashboard metric cards, upcoming expiries, server-side Tenant search/filter/pagination and a read-only Tenant detail view.

The existing Add shop, Plans, Web addresses, Activity and support Audit workflows remain connected to their existing endpoints/services. The Plans and Activity workspaces now load a bounded first page (maximum 50 Tenant choices) rather than an unbounded Tenant list.

The corresponding generated public JavaScript copies were synchronized byte-for-byte from their resource source files:
- `public/assets/js/admin/app.js`
- `public/assets/js/api/phase-one.js`
- `public/assets/js/shared/workspace-registry.js`
- `public/assets/js/components/audit-panel.js`

No new Sass styling or CSS output change is required for this implementation; existing responsive Bootstrap/UI styles are reused.

## 2. Security and architecture review

Source inspection confirmed:
- The Admin routes remain separate from Shop routes.
- The existing Platform Admin Shield group and `platform.access` permission are reused.
- The existing `PlatformAdminFilter` enforces the 15-minute recent-MFA session.
- `PlatformAdminEmail2FA` calls `PlatformMfaService::establish()` only after Shield verification returns a redirect and the same Platform Admin is authenticated.
- Shield 1.4.1 defines the enabled-account field as `users.active` and includes `deleted_at`; the group membership table uses the `group` column.
- No new permission code, business table, migration, cache, offline storage, impersonation or Tenant membership was introduced.
- The new routes are GET-only and read-only.
- Platform Admin continues to act through platform authority, not a fabricated Shop membership.
- No Phase 10 transport or recovery functionality was introduced.

This is source-level evidence only. Runtime MFA expiry, session, CSRF, route and response behavior still require local verification.

## 3. Engineering checks and limitations

### Completed source checks
- Inspected the exact Phase 9 WP1 contract and current main-branch implementation before editing.
- Inspected the pinned Shield 1.4.1 migration for the user enabled/deleted and group membership schema.
- Inspected the current Tenant, Shop, subscription, domain, Device and Sync-generation migrations.
- Confirmed the generated JavaScript files are byte-for-byte identical to their corresponding resource sources.
- Reviewed the new query/DTO allowlists for internal identifier and credential leakage.
- Reviewed route filters, parameter allowlists, bounded page sizes and the removal of the former N+1 directory loop.
- Confirmed no Phase 9 schema migration or permission change was added.

### Not run in this environment
The repository connector does not provide a local XAMPP/MySQL/Node runtime. Therefore the following have NOT been claimed as passed:
- `php -l` syntax checks against the complete local checkout.
- `npm ci` and `npm run build` in the local project.
- CI4 route execution and API response checks.
- MySQL fixture reconciliation or EXPLAIN/EXPLAIN ANALYZE.
- Browser interaction, responsive, offline, storage/cache or security manual checks.
- XAMPP database migration-state checks.
- The 38 Owner manual verification cases.

No automated test framework or test suite was introduced. No production deployment was performed.

## 4. Required local preparation

Use the approved WP1 branch or check out the corresponding PR branch in the local DawaTrack working copy. Preserve any local work before switching branches.

From the repository root:

```bash
npm ci
npm run build
```

Then:
1. Start Apache and MySQL in XAMPP.
2. Confirm the local `.env` points to the intended disposable/local DawaTrack database, not production.
3. Confirm existing migrations are current with `php spark migrate:status`. WP1 adds no migration.
4. Run PHP syntax checks on the changed PHP files:

```bash
php -l app/Controllers/Api/PlatformOperationsController.php
php -l app/Modules/Platform/Application/PlatformDashboardQuery.php
php -l app/Modules/Platform/Application/PlatformTenantDirectoryQuery.php
php -l app/Modules/Platform/Application/PlatformTenantDetailQuery.php
php -l app/Config/Routes.php
```

5. Sign in through the normal Platform Admin login and complete the email second-factor challenge.
6. Open the Admin workspace and select Overview.

Do not use a production database for fixture creation or negative tests.

## 5. Manual API and UI verification

Use the authenticated browser session and browser Developer Tools Network panel. Do not paste session cookies or MFA material into a public issue.

### Dashboard
- Confirm `GET /api/v1/platform/operations/dashboard` returns 200 for a fresh authorized Platform Admin.
- Compare Tenant and Shop status counts with the local database.
- Reconcile current Trial/Paid, Expired and Scheduled counts using the half-open UTC period rule: `starts_at <= as_of AND ends_at > as_of`.
- Confirm enabled Platform Admin count uses only active, non-deleted Shield users in the platform_admin group.
- Confirm Device metric is unavailable rather than zero if the Device schema is incomplete.
- Confirm Sync readiness says READY only when exactly one active generation exists.
- Confirm no more than ten upcoming expiries are returned.
- Confirm no Sales, Cash, Debt, Stock valuation or Profit fields appear.

### Tenant directory
- Confirm `GET /api/v1/platform/operations/tenants?page=1&per_page=25` returns no more than 25 records.
- Test page size 50 and rejection of 0 or values above 50.
- Test every allowed Tenant, Shop and entitlement filter.
- Test search by Tenant public UUID/name, Shop public UUID/name and hostname.
- Test combined filters, empty results and a large fixture across pages.
- Confirm ordering is deterministic and no Tenant is duplicated under the one-Shop-per-Tenant invariant.
- Inspect the response to confirm it contains no internal numeric sort key, credentials, tokens, subscription event history or operational financial data.

### Tenant detail
- Select a Tenant and confirm `GET /api/v1/platform/operations/tenants/{public-uuid}` returns the safe detail summary.
- Test an unknown and malformed UUID; both must return the safe 404 response.
- Confirm the response does not expose internal IDs, credentials, Device verifiers or raw Audit data.
- Confirm multiple current periods are flagged rather than presented as one normal entitlement.
- Confirm no-domain and Device-unavailable states are clear.

### Security and existing workflows
- Test anonymous, non-platform, missing-permission and expired-MFA requests.
- Confirm visiting the login/MFA redirect without completing the factor does not refresh the MFA timestamp.
- Confirm all new endpoints are GET-only and protected by platform.access.
- Confirm offline state does not display cached Admin data or permit mutations.
- Inspect localStorage, sessionStorage and IndexedDB for Admin API data; none should be stored.
- Confirm Add shop, Plans, Web addresses, Activity and scoped support Audit remain functional.
- Verify Overview, Tenants, Tenant detail and existing workflows at phone, tablet and desktop widths.

## 6. Manual acceptance register

All WP1-M01 through WP1-M38 in the Detailed Implementation Contract remain **NOT RUN** until executed in the local environment. Record actual environment, timestamp, actor, fixture, steps, expected result, observed result, evidence reference and reviewer. A source review, generated-file sync or build result is not manual acceptance.

## 7. Stop condition

After local preparation and engineering checks, stop and provide the Owner with the observed results. Resolve any failure and repeat the affected checks. WP1 remains pending until the Owner completes local manual verification and explicitly accepts WP1.

WP2 must not commence before that acceptance.
