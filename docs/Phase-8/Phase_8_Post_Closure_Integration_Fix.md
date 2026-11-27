# Phase 8 — post-closure integration regression correction

**Prepared:** 27 November 2026  
**Target:** `main` after PR #55  
**Correction branch:** `fix/phase-8-dashboard-api-contract-20261127`

## Observed regression

After the Owner-approved Phase 8 source integration, PR #55 was merged to `main` and changed shared WP1 files. It removed the WP2 Stock, Customer Debt and Reconciliation GET route registrations and changed the Sales report controller/query contract. The WP3 dashboard remained on the earlier Sales request/response contract.

The resulting local Home behavior matched the Owner's screenshot:
- Sales returned HTTP 422 because the dashboard sent `group_by`, `from` and `to`, while the current WP1 query accepts `period`, `from_date` and `to_date`.
- Stock, Customer Debt and Reconciliation returned HTTP 404 because their route registrations were absent from current `main`.

The completed Sass build and migration output did not cause these API failures. Sass output consisted of deprecation warnings; the frontend asset build completed.

## Correction

1. Restore the three existing WP2 GET routes to `OperationalReportController::stock`, `customerDebt` and `reconciliation`. They remain authenticated and covered by the existing `api/v1/shop/reports/*` throttle filter.
2. Align the dashboard Sales request with the current WP1 contract: `period=TODAY` or `period=CUSTOM&from_date=...&to_date=...`.
3. Align the Sales card with the current WP1 response: `period.from_date/to_date/timezone/refreshed_at`, `sales.amount/counts`, `payments.*_amount`, and masked `gross_profit.amount`.
4. Preserve the dashboard's selected custom period when opening the existing `#sales-reports` workspace.
5. Remove a stale Sales render branch that referenced the former report component and undeclared state.
6. Extend WP3 static source/UI assertions to cover exact route/controller mappings and the current WP1 API request/response contract.

No new report endpoint, domain query, table, migration, permission, export capability, cache or duplicate calculation is introduced.

## Verification status

This is a source correction candidate, not yet locally verified. The Owner must run PHP syntax checks, the WP3 PHP/Node static contracts, `npm run build`, `php spark routes` and native browser/API checks against the local XAMPP database before merging. The source integration correction must not be described as runtime-verified until those checks pass.
