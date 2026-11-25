# Phase 8 WP1 — Sales reports delivery report

**Implementation branch:** `codex/phase-08-wp1-sales-reports`  
**Base:** `132ccdb5037a3b5cfc488f202509c703cd5f45d5`  
**Status:** Implementation changes prepared; awaiting native verification and Owner acceptance. WP2 has not started.

## 1. Delivered source changes

- `app/Modules/Sales/Infrastructure/SalesReportQuery.php` — Shop-scoped repeatable-read report projection; Shop timezone period handling; signed Sales, Cash, Transfer and Credit totals; source-evidence reconciliation; permission-gated historical Gross Profit and cost-state caveat.
- `app/Controllers/Api/SalesReportController.php` — transport-only GET controller using the existing CommercialController envelope and no-store boundary.
- `app/Config/Routes.php` — `GET /api/v1/shop/reports/sales`.
- `app/Controllers/Api/ShopController.php` — presentation capability `view_sales_workspace` derived from existing `sale.view OR report.view`; no new grant.
- `resources/js/shared/workspace-registry.js` — existing Sales workspace now supports report-only access and a Sales report hash alias.
- `resources/js/components/sales-reports-panel.js` — plain-English report filters, amounts, explanation, date/timezone/freshness and access-aware source link.
- `resources/js/components/sale-history-panel.js` — existing Sales workspace switches between report and Sales/Returns history without changing existing record flows.

## 2. Source rules preserved

- Posted evidence only; drafts are excluded.
- Sale, Return and compensation signs are explicit and source-linked.
- Cash/Transfer use posted Sale payment receipt/refund evidence.
- Credit uses posted Receivables and sale-owned return/cancellation adjustments; collections and generic bill corrections are not counted as new Sales.
- Each saved Sales header is checked against its own item lines before aggregation; payment/credit composition is then checked with MySQL DECIMAL arithmetic. Inconsistent evidence is rejected, not silently presented.
- Gross Profit uses historical Sale Item COGS snapshots and the existing Inventory cost-state reconstruction. It is hidden unless `sale.profit.view` is independently granted.
- Shop-local date boundaries are converted to UTC and filtered using `posted_at`.
- No schema, migration, SQL twin, index, dependency, Audit write, new permission, duplicate ledger, export or offline report storage is added.

## 3. UI requirement

The report is inside the existing Shop Sales workspace. Staff with `report.view` can see the report; Staff with `sale.view` can see Sales/Returns history; users with both can switch between them. Primary Owner behavior remains role-derived. Gross Profit visibility remains separately governed by `sale.profit.view`.

The labels and explanations use everyday words: money from sales after returns, Cash after refunds, bank transfers after refunds, customers allowed to pay later, and money left after paying for goods. The Gross Profit meaning is explained without expecting accounting knowledge. It displays Shop dates, Shop timezone and last refresh time.

## 4. Verification status

| Verification | Status |
|---|---|
| Repository source review and contract alignment | 21/21 source-level assertions manually confirmed against the feature-branch files; this is not a PHP test execution |
| Documentation/source consistency | Prepared; review in PR |
| Native PHP syntax/lint | Not run in this environment |
| WP1 source contract guard (`php tests/contracts/p8-wp1.php`) | Added and extended; PHP execution not run in this environment |
| CodeIgniter route/service integration | Not run in a native app environment |
| MySQL 8.4 SQL execution and evidence fixtures | Not run |
| EXPLAIN/representative-volume performance | Not run |
| Staff report-only / Sales-only / both / Owner role browser checks | Not run |
| Gross Profit masking and historical provisional-cost cases | Not run |
| Mobile/tablet/desktop and keyboard/accessibility checks | Not run |
| Frontend build and generated asset verification | Full `npm run build` not run. Changed published JavaScript modules were copied byte-for-byte from their `resources/js` sources; the release-manifest check remains pending. |
| Owner hands-on manual acceptance | Pending |

The absence of a native application/database/browser session is not represented as a pass. The manual guide and result register identify the checks required before WP1 acceptance. No result is marked Pass without an observed run.

## 5. Next gate

Run the WP1 native verification guide against the exact PR candidate and a MySQL 8.4 test database with synthetic Shop records. Resolve any failed case, then obtain explicit Owner acceptance before starting WP2.
