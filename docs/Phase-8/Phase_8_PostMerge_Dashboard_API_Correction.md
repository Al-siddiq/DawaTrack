# Phase 8 post-merge dashboard API compatibility correction

**Date:** 27 November 2026  
**Target:** current `main` API/source contract after the Owner reported the merged Home dashboard displayed four API errors.  
**Branch:** `fix/phase-8-main-dashboard-api-contract`

## 1. Observed local evidence

The Owner's screenshots show:

- Frontend asset build completed and generated `resources/js/shop/render.js`.
- `php spark migrate` completed successfully.
- The Shop Home dashboard rendered.
- Sales and payments returned HTTP 400 with the message that unsupported or protected fields were submitted.
- Stock, Customer Debt and Reconciliation returned HTTP 404.

Sass `@import` and Bootstrap color-function messages are deprecation warnings. The build proceeded through the asset-generation step; those warnings do not explain the API responses.

## 2. Root causes confirmed by source inspection

The current `main` branch contained the WP2 report query classes and `OperationalReportController`, but `app/Config/Routes.php` registered only the Sales report route. The three dashboard WP2 URLs therefore had no matching route and returned 404.

The current Sales query allowlist is exactly `period`, `from_date`, and `to_date`. The dashboard instead sent `group_by`, `from`, and `to`, producing the unsupported-field response. The current Sales response shape is `period.from_date`, `period.to_date`, `period.timezone`, `sales.*`, `payments.*`, `gross_profit.amount`, and `profit_visible`; the dashboard had been rendering a different response shape.

The three WP2 query classes accept `per_page` values of 20 or 50, not 1. The previous dashboard summary requests therefore also did not conform to the source query validation rules, even though the missing routes prevented that validation from being reached.

The existing Sales report screen uses the `#sales-reports` route and the `period/from_date/to_date` contract. The dashboard had been emitting a different hash form. The Sales history component also contained a stale conditional referencing an undefined report component/state.

## 3. Corrections on this branch

- Registered authenticated GET routes for Stock, Customer Debt and Reconciliation, mapped to the existing `OperationalReportController` methods. No new API or controller was created.
- Kept the existing Sales route mapped to the current `SalesReportController::index` method.
- Changed dashboard Sales requests to `period=TODAY` or `period=CUSTOM&from_date=...&to_date=...`.
- Changed the Sales card to display only the actual current API response fields. It does not invent refund, payment-mix or other fields absent from the current response.
- Changed WP2 dashboard pagination to the accepted `page=1&per_page=20`.
- Added client-side checks for missing or reversed custom Sales dates.
- Updated Sales drill-down to the existing `#sales-reports` route and preserve the selected custom dates in its hash query.
- Updated SalesReportsPanel to initialize from those carried date parameters.
- Removed the stale undefined report-render branch from SaleHistoryPanel so it renders the existing SalesReportsPanel.
- Updated the WP3 static contracts and the composition, performance and manual-verification documentation to match the real source API contract.

## 4. Scope and safety

This is a compatibility correction to existing Phase 8 report routes and the existing Shop Home composition. It adds no tables, columns, migrations, SQL twins, indexes, dependencies, permissions, Staff grants, report cache, offline persistence, export or new business calculations. Existing source query authorization and Shop scoping remain authoritative.

No database migration is introduced by this correction. The successful migration output in the Owner's screenshot is not evidence that the missing HTTP routes were registered; route registration is an application-code concern.

## 5. Verification still required

Source-level contract checks have been updated, but they have not been executed in the Owner's local Windows/XAMPP environment from this delivery branch. The Owner should run the commands in the PR handover and verify:

1. `php spark routes | findstr /I "shop/reports"` lists all four report GET routes.
2. `php tests/contracts/wp8-wp3.php` and `node tests/contracts/wp8-wp3-ui.mjs` pass.
3. Changed PHP/JavaScript syntax checks and `npm run build` pass.
4. The Home dashboard returns successful responses for all authorized report cards.
5. Sales period selection and drill-down preserve the selected dates.
6. Existing permission masking, Shop scope, request throttling and source freshness remain correct.

Do not mark the correction as locally verified until those checks are observed. No production deployment is implied.
