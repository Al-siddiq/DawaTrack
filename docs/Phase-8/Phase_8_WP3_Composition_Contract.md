# Phase 8 WP3 — Shop Dashboard Composition Contract

**Package:** WP3 — Shop Dashboard, Approved Export and Phase 8 Integration  
**Branch:** `feature/phase-8-wp3-shop-dashboard`  
**Predecessors:** WP1 and WP2 explicitly accepted by the Owner. WP2 closure: [2026-11-26 Owner acceptance](../acceptance/2026-11-26-phase-08-wp2-owner-acceptance.md).  
**Authority:** [Phase 8 implementation plan](Phase_8_Implementation_Plan.md), Phase 8 roadmap, Technical Blueprint, schema v1.1, module map, API conventions, security foundation, permission catalogue, UI architecture, navigation/access policy and plain-English policy.

## 1. Composition boundary

The existing Shop Home page is the operational dashboard surface. It retains its current greeting, shortcuts, starting-cash reminder and unfinished-sale continuation behavior. A new dashboard summary section is added to that page; it is not a replacement Home application or a new navigation/router framework.

The dashboard is a read-only composition of the four accepted report APIs. It consumes their returned summary fields directly and performs no domain aggregation, money arithmetic, Stock valuation, Receivable calculation or Reconciliation reconstruction.

| Card family | Authoritative query owner | Existing route |
|---|---|---|
| Sales and payment mix | Sales — `SalesReportQuery` | `GET /api/v1/shop/reports/sales` |
| Current Stock | Inventory — `StockReportQuery` | `GET /api/v1/shop/reports/stock` |
| Customer balances | CustomerDebt — `CustomerDebtReportQuery` | `GET /api/v1/shop/reports/customer-debt` |
| Finished shop checks | Reconciliation — `ReconciliationReportQuery` | `GET /api/v1/shop/reports/reconciliation` |

No dashboard-specific SQL, report service, API route, database table, materialized summary, duplicate balance or new module is introduced. Existing source endpoints continue to own all calculations and independently authorize every read.

## 2. Permission and access contract

The dashboard summary is rendered only when the current server-provided permission list includes `report.view`. Each card is independently gated by the same effective source capability used by its report page:

- Sales: `report.view`. Gross Profit is displayed only when the returned WP1 `profit_visible` is true (the source API independently checks `sale.profit.view`).
- Stock: `view_stock_report` (`report.view` + `inventory.view`).
- Customer Debt: `view_customer_debt_report` (`report.view` + `customer.view` + `debt.view`).
- Reconciliation: `view_reconciliation_report` (`report.view` + `reconciliation.view`). Variance counts/values are displayed only when the source response's `variance_visibility` permits them.

Capabilities only control presentation. They are not authorization credentials. Each existing API independently resolves the current server-owned Shop context, checks its own permissions and scope, and returns its existing safe response envelope. No client Tenant, Shop, actor or permission parameter is accepted.

READ_ONLY permits these authorized GET reads. BLOCKED/CLOSED must not trigger dashboard report requests and remain governed by the existing Shop shell and AccessGate. The dashboard adds no Staff grant and no permission code.

## 3. Period and date semantics

The Sales card uses the same WP1 Shop-local date range, 366-day maximum and report grouping semantics as the Sales report. The selected `from` and `to` dates are passed to the existing Sales report route and are carried into the Sales drill-down hash so the Sales report opens on the same selected period.

WP2 has deliberately different source semantics and accepts no historical period filter:
- Stock is the current Stock Position.
- Customer Debt is the current outstanding/overdue position as of the Shop-local date.
- Reconciliation is persisted finalized evidence, not a reconstruction for the Sales period.

Those cards are explicitly labelled **Current Stock**, **Current customer balances** and **Latest finished checks**. Their own Shop timezone and refresh/finalization metadata are shown. The dashboard must never imply that current Stock, current Debt or latest finalized Reconciliation is a historical total for the selected Sales period.

All source reports expose their existing Shop timezone and freshness information. Sales `refreshed_at` is the WP1 UTC timestamp; WP2 provides `as_of` UTC and Shop-local `refreshed_at`. The dashboard presents each source's own refresh time and does not claim a single atomic cross-report snapshot.

## 4. Refresh, version and change dependencies

No dashboard cache, local persistence, polling timer, background sync or Phase 10 offline behavior is added.

A dashboard load requests each authorized report once. The WP2 requests use `per_page=1&page=1` because the dashboard consumes summary values only; the report query still calculates the complete filtered summary from its authoritative source. The Sales request uses the selected period and `group_by=MONTH` to keep its dashboard payload compact.

The response's existing `as_of` or `refreshed_at` value is the per-source freshness/version marker. A refresh re-reads the relevant existing source endpoint. A changed Sales period re-requests only Sales; it does not unnecessarily reload current Stock, Debt or finalized Reconciliation. Concurrent duplicate requests for the same source are prevented, and requests are aborted when the Home dashboard unmounts.

The four source reads are independent, not one database transaction. The interface shows each source's own refresh time and reports partial failures per card. It never labels separately read sources as one globally consistent snapshot. Existing `Cache-Control: no-store, private` remains authoritative. The existing `SensitiveRateLimitFilter` is applied to all four report GET paths with one shared per-signed-in-user bucket of 20 requests per 60 seconds; excess requests receive the established 429 `RATE_LIMITED` response and `Retry-After` header. This is request protection, not a cache or substitute for bounded report queries.

No request is made for an unauthorized card. A 401/403 response clears that card's data. A network failure may retain only the last in-memory successful result with an explicit stale warning while online. When offline, live report figures are cleared and an offline message is shown; no report data is stored in localStorage, IndexedDB or a service-worker cache.

## 5. Dashboard fields and presentation

- **Sales:** sales after returns; Sales before returns; returns/cancellations; Cash received/refunded; Transfer received/refunded; Credit created/adjusted; Gross Profit before expenses only when `profit_visible`. No Net Profit or expense deduction.
- **Current Stock:** Product count; low-stock count; negative-stock count; confirmed Stock value and valuation completeness. No total quantity across unlike units.
- **Current customer balances:** outstanding amount; overdue amount; overdue Customer count; partially paid Customer count. No private Customer fields.
- **Latest finished checks:** finalized total and Cash/Stock/Full counts; latest finalized time; Cash variance check count and Stock shortage/surplus item counts only when `variance_visibility` allows them. No blind expected values, review reasons or private evidence.

All money remains a decimal string from the source report and is formatted for display only. The dashboard does not recompute totals or derive new financial values. The source card links to its existing report/detail route.

## 6. Failure and access-mode behavior

Each source card has independent loading, empty, error, stale and refresh states; one unavailable source does not suppress other authorized cards. Offline clears live figures. No report requests are made in BLOCKED/CLOSED. READ_ONLY is identified as read-only where the Shop shell presents that mode; the dashboard has no mutation controls.

## 7. Export disposition

**Export is not approved and is not implemented in this WP3 candidate.** The Owner has not specified a report family, fields, date/row bounds, format, audience, distinct authority or Audit contract. Therefore there is no export route, UI, permission, file generation, download path or export Audit event. P8-34 verifies that export is absent unless a complete separate Owner approval is recorded.

## 8. Performance and acceptance

No new cache, index, migration, SQL twin, table, dependency, framework or CI workflow is introduced. Performance evidence must measure the four existing report routes against pilot-shaped MySQL data and capture query plans; source inspection is not a substitute.

Manual cases P8-25–P8-36 remain Not run until actual supported-environment observations are recorded. See [WP3 manual verification guide](Phase_8_WP3_Manual_Verification_Guide.md) and [manual result register](Phase_8_WP3_Manual_Results.csv).
