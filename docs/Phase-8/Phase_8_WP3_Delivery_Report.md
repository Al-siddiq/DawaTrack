# Phase 8 WP3 — Implementation Delivery Report

**Date:** 26 November 2026 (Africa/Lagos)  
**Package:** WP3 — Shop Dashboard, Approved Export and Phase 8 Integration  
**Branch:** `feature/phase-8-wp3-shop-dashboard`  
**Base:** Owner-accepted WP2 source candidate `feature/phase-8-wp2-stock-debt-reconciliation`  
**Predecessors:** WP1 and WP2 Owner acceptance records are preserved in `docs/acceptance/`.  
**Status:** Implementation candidate delivered for local build, native verification and Owner manual acceptance. Not merged.

## 1. Delivery outcome

WP3 adds a permission-aware operational dashboard section to the existing Shop Home. It composes the accepted WP1 Sales report and WP2 Stock, Customer Debt and Reconciliation reports using their existing GET endpoints and source-owned query definitions.

The dashboard does not add a Reporting module, dashboard-specific SQL, a competing financial/Stock/Debt calculation, a new dashboard API, a new navigation framework or a second reporting truth. The existing Shop Home shortcuts, starting-cash reminder and unfinished-sale workflow remain in place.

## 2. Delivered implementation

### Dashboard and source composition

- Added `OperationalDashboardPanel` and mounted it inside the existing `WorkspaceHome`.
- Sales card consumes `GET /api/v1/shop/reports/sales`, displays Sales after returns, returns/cancellations, Cash and Transfer received/refunded, Credit created/adjusted and Gross Profit only when WP1 returns `profit_visible=true`.
- Payment-mix mismatch warning follows the WP1 source report's `payment_mix_matches_sales` result.
- Current Stock card consumes the existing Stock report and displays product/low/negative counts, confirmed Stock value and valuation completeness.
- Current Customer balances card consumes the existing Customer Debt report and displays outstanding/overdue totals and customer counts.
- Finished shop checks card consumes the existing Reconciliation report and displays finalized counts plus only those variance counts permitted by `variance_visibility`.
- Dashboard cards do not recalculate source values. All money remains a source decimal string and is formatted only for display.
- WP2 summary calls use `page=1&per_page=1`; Sales uses the existing bounded WP1 date contract and monthly grouping.

### Period, freshness and drill-down

- Added one selected Sales date range, using the existing WP1 366-day Shop-local date rules.
- Carried the applied Sales dates into the Sales report hash so the detailed report opens on the same period.
- Current Stock, current Customer Debt and finalized Reconciliation are explicitly labelled as current/latest source states, not historical totals for the selected Sales period.
- Each card shows its own source freshness marker and Shop timezone. The four source requests remain independent; the dashboard does not claim a single atomic cross-report snapshot.
- Added cancellable source requests, one in-flight request per report family, manual refresh, online/offline handling and independent card error/stale states.
- No polling, localStorage, IndexedDB, service-worker report cache or Phase 10 offline synchronization is introduced.

### Security and request protection

- Dashboard visibility requires existing `report.view`; each source card uses the existing server-derived capability for its source permissions.
- Gross Profit/COGS remains controlled by WP1 `sale.profit.view`; Reconciliation variance summaries remain controlled by WP2 `reconciliation.review` and the existing blind-read policy.
- Every source endpoint independently rechecks its own authority and server-resolved Tenant/Shop scope.
- READ_ONLY permits report reads; BLOCKED/CLOSED does not trigger dashboard report requests.
- Applied the existing `SensitiveRateLimitFilter` to all four report GET paths. They share a per-signed-in-user bucket of 20 requests per 60 seconds and use the established 429 `RATE_LIMITED` / `Retry-After` response.
- No Staff grants or new permission codes are added.

### Export disposition

**Export is not approved and is not implemented.** The Owner has not approved an exact report family, fields, period/row bounds, format, audience, distinct authority or Audit contract. No export route, permission, UI action, file generation or download path is introduced. P8-34 records this disposition for manual verification.

## 3. Documentation and verification artifacts

- `Phase_8_WP3_Composition_Contract.md`
- `Phase_8_WP3_Source_Matrix.csv`
- `Phase_8_WP3_Manual_Results.csv` — P8-25–P8-37 reserved, all currently Not run.
- `Phase_8_WP3_Manual_Verification_Guide.md`
- `Phase_8_WP3_Performance_Notes.md` — query-plan and performance evidence register; all measurements remain Not run.
- `Phase_8_WP3_Operations_Handover.md` — permissions, freshness, rate limits and safe recovery.
- `Phase_8_WP3_Delivery_Report.md`
- WP2 acceptance: `docs/acceptance/2026-11-26-phase-08-wp2-owner-acceptance.md`.

Static contract test files:

- `tests/contracts/wp8-wp3.php`
- `tests/contracts/wp8-wp3-ui.mjs`

## 4. Schema, module and dependency controls

- No new module, table, column, materialized view, migration, SQL twin, index, model, dependency, framework, CI workflow, cache, export route or export permission.
- No generated renderer or `public/assets/` file was hand-edited. The existing locked `npm run build` pipeline must generate/copy the updated resources.
- The existing four report APIs remain the sole source of dashboard figures.
- The dashboard's four independent reads are not one database transaction; freshness is displayed per source.

## 5. Owner-requested corrections

The Owner reviewed the Home layout and clarified the intended hierarchy: personal greeting first; Shop overview second; Continue an unfinished sale third; and the four primary quick-action cards fourth. The quick-action section heading is **What would you like to do?**. The starting-cash reminder and READ_ONLY plan shortcut are secondary prompts and appear after the four primary actions. `WorkspaceHome` and the WP3 contract tests now enforce this hierarchy. P8-37 remains Not run pending local browser verification.

The Finished shop checks card returned a generic server error. Source tracing identified a bind-parameter mismatch in `ReconciliationReportQuery`: the finalized-session list query supplied duplicate Tenant/Shop values in addition to the derived variance-subquery and outer Shop-scope values. The bind array now starts empty and adds only the values for the actual SQL placeholders, in SQL order. The PHP static contract test covers this correction.

## 6. Verification status — not claimed as passed

The repository changes and documentation are committed on the GitHub branch. I cannot execute commands inside the user's local Windows/XAMPP PHP/MySQL/Node environment from this delivery session. Therefore none of the following is represented as Pass:

- PHP syntax checks for changed filter/config and WP3 static test;
- Node syntax checks for changed dashboard/Home/Sales report files;
- `php tests/contracts/wp8-wp3.php`;
- `node tests/contracts/wp8-wp3-ui.mjs`;
- locked `npm run build` and generated `public/assets/`;
- native authenticated HTTP, rate-limit, response-header and permission checks;
- P8-25–P8-37 manual cases;
- EXPLAIN/EXPLAIN ANALYZE, pilot-shaped performance, request-storm and cross-Shop isolation evidence;
- phone/tablet/desktop, keyboard and accessibility checks.

The manual register remains Not run until the Owner records observed results. Static source assertions and source inspection are not native acceptance.

## 7. Owner handover and stop point

WP3 is ready for local syntax/static checks, the locked frontend build and Owner-led native verification. The exact commands, actor matrix, expected outcomes and evidence requirements are in the Manual Verification Guide.

**Stop here. Do not begin another package, merge the WP3 branch, integrate to `main`, deploy or release until the Owner reviews P8-25–P8-36 evidence and explicitly accepts WP3.** The WP3 pull request is a draft targeting the accepted WP2 branch so cumulative WP1/WP2 changes are included exactly once. Production deployment and operational release remain separate decisions.
