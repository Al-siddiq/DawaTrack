# Phase 8 WP1 — Sales, Payment Mix and Gross Profit Reports

**Status:** Implementation candidate prepared for Owner review; native acceptance is pending.  
**Prepared:** 25 November 2026 (Africa/Lagos).  
**Base:** `main` at `f5b815662807d96f75c257b62de0ccd7cbb98111`.  
**Branch:** `feature/phase-8-wp1-sales-reporting`.

## 1. Delivered implementation candidate

- Added read-only Sales-owned `SalesReportQuery` with Shop-local date boundaries, bounded 366-day range, daily/monthly grouping, Sales/Return totals, Cash/Transfer receipts/refunds, Credit snapshots/adjustments and permission-gated historical COGS/Gross Profit.
- Added authenticated `GET /api/v1/shop/reports/sales` route and transport-only `SalesReportController`.
- Reused existing `report.view` and `sale.profit.view`; no permission code or Staff grant was added.
- Added report-only Shop navigation capability while preserving independent Sales history permission.
- Added responsive Sales summary UI inside the existing Sales workspace using the existing Vue runtime/component/API conventions.
- Added plain-English report labels, period explanations, cost/profit explanation, payment-mix warning, refresh time, loading/error/empty states and mobile-friendly period cards.
- Added WP1 API/source/UI contracts, source traceability matrix and the 12-case manual result register.

## 2. Financial behavior

- Posted Sale rows contribute positive Sales; posted Return/compensation rows use their persisted opposite `kind` to contribute the negative/positive effect established by the existing compensation service.
- Payment rows use persisted `RECEIPT`/`REFUND` and `CASH`/`TRANSFER` evidence.
- Credit comes from Sale credit snapshots plus Sale-linked signed Receivable adjustments. Debt collections are not added as Sales.
- COGS comes only from historical `sale_items.line_cogs_amount` snapshots and is not recalculated from current Stock costs.
- Gross Profit is Sales after Returns minus net historical product cost. Expenses are not deducted and no Net Profit metric exists.
- Profit/COGS query and response fields are omitted unless `sale.profit.view` is granted.

## 3. Database and dependency decision

No schema migration, SQL twin, new index, table, cache, dependency or new permission was added. Existing Sales/Payment indexes are used. No production data, environment secrets or generated assets were changed in this branch.

## 4. UI role and plain-language behavior

The existing Sales menu destination is available to a user with either `sale.view` or `report.view`. A report-only Staff member sees the report but does not gain Sales history access. A user with both permissions can move between Sales history and Sales summary. The server continues to enforce `report.view` on the report route and `sale.view` on Sales history.

The report uses everyday labels and explains Credit and Gross Profit. The Gross Profit section is explicitly marked “before shop expenses”. It does not use formal Net Profit language or expose cost values to a user without `sale.profit.view`.

## 5. Verification status

- GitHub source updates: prepared on the feature branch.
- Native PHP/CodeIgniter HTTP execution: **Not run in this environment**.
- Native MySQL reconciliation fixtures and EXPLAIN/EXPLAIN ANALYZE: **Not run**.
- Browser/device/keyboard/accessibility review: **Not run**.
- P8-01–P8-12: remain **Not run** in [the manual result register](Phase_8_WP1_Manual_Results.csv).
- The implementation candidate has not been represented as Owner-accepted or production-deployed.

The source matrix is [Phase_8_WP1_Source_Matrix.csv](Phase_8_WP1_Source_Matrix.csv); API, source and UI rules are in [Phase_8_WP1_Integration_Contracts.md](Phase_8_WP1_Integration_Contracts.md).

## 6. Owner acceptance gate

Review the candidate and execute P8-01–P8-12 in the supported native Shop environment. Any observed mismatch must be corrected and retested before WP1 is accepted and WP2 begins. Do not mark an unobserved test as Pass.
