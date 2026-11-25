# Phase 8 WP2 — Stock, Customer Debt and Reconciliation Report Contracts

**Date:** 25 November 2026 (Africa/Lagos)  
**Status:** Implementation candidate; static and native verification pending.  
**Predecessor:** Owner-accepted Phase 8 WP1. See [WP1 Owner acceptance](../acceptance/2026-11-25-phase-08-wp1-owner-acceptance.md).  
**Authority:** [Phase 8 implementation plan](Phase_8_Implementation_Plan.md), the Technical Blueprint, Database Schema Design v1.1, module map, API conventions, security foundation, permission catalogue, navigation/access policy and plain-English UI policy.

## 1. Ownership and boundaries

WP2 implements three read-only report queries in their existing owning modules:

- **Inventory:** `Inventory\\Infrastructure\\StockReportQuery` owns current Stock Position interpretation and cost-state-aware Stock valuation.
- **CustomerDebt:** `CustomerDebt\\Infrastructure\\CustomerDebtReportQuery` owns outstanding, settlement and overdue interpretation using the existing Receivable balance projection.
- **Reconciliation:** `Reconciliation\\Infrastructure\\ReconciliationReportQuery` owns summaries of persisted finalized Cash/Stock/Full evidence and signed variances.

`OperationalReportController` is transport-only. It maps each GET request to its owning query and the established canonical response envelope. It does not calculate report values.

No module writes another module's records. The reports do not create a second balance, Stock, reconciliation or transactional truth.

## 2. Shared API and security contract

Prefix: `/api/v1/shop`.

All three routes are authenticated GET routes. `CommercialController::execute()` resolves the current server-owned `ShopContext`, applies the existing AccessGate behavior, returns the canonical API envelope/request ID and sets `Cache-Control: no-store, private`.

No Tenant ID, Shop ID, Customer ID, actor ID or permission claim is accepted from the browser as authority. Every query scopes its SQL by the resolved `tenant_id` and `shop_id`.

Every endpoint requires the existing `report.view` plus the source-domain view permissions listed below. READ_ONLY remains read-permitted. BLOCKED/CLOSED, inactive membership, foreign Shop and unauthorized access remain denied by existing context/AccessGate/permission behavior. Staff grants are not expanded.

Pagination is bounded: `page` defaults to 1 and is capped at 100000; `per_page` defaults to 20 and is capped at 50. Results use deterministic ordering. Unsupported query keys and invalid filter values return the standard safe `VALIDATION_FAILED` envelope (422).

No report read creates Audit events or mutates source evidence. No cache, export route, export permission, migration, SQL twin, table, index, dependency or new permission is introduced.

## 3. Inventory Stock report

**Route:** `GET /api/v1/shop/reports/stock`  
**Owner:** Inventory  
**Required permissions:** `report.view` AND `inventory.view`  
**Source:** `stock_positions` joined to same-Tenant Products; current persisted `expected_quantity`, `low_stock_threshold`, `average_unit_cost` and `cost_basis_state`.

### Query filters

| Field | Accepted values | Default |
|---|---|---|
| `state` | `ALL`, `LOW`, `NEGATIVE`, `NORMAL`, `RETAINED`, `UNESTABLISHED` | `ALL` |
| `search` | Literal Product name, maximum 100 characters | empty |
| `page` | Integer 1–100000 | 1 |
| `per_page` | Integer 1–50 | 20 |

### Definitions

- Low stock is derived exactly as `expected_quantity <= low_stock_threshold`; no second low-stock flag is stored.
- Negative stock remains visible and separately counted. It is not silently corrected.
- Confirmed Stock value is the SQL DECIMAL aggregate of `expected_quantity × average_unit_cost`, rounded to two currency decimals per Product position and then summed, matching the Inventory line-amount precision, only for positive quantity with `cost_basis_state='VALID'`.
- `RETAINED` and `UNESTABLISHED` cost states are shown as states, not false-precision valuations. Their retained/unknown basis is excluded from confirmed Stock value. Negative positions are also excluded from confirmed value.
- The response says whether valuation is complete and reports retained, unestablished and negative-position counts separately.
- No quantity total is formed across Products with different units.
- The item list exposes no internal numeric IDs. Average unit cost and item Stock value are returned only for a positive position with a VALID basis.

### Response data

`shop` (Shop timezone and currency), UTC `as_of`, Shop-local `refreshed_at`, `summary` (product/low/negative/valued/retained/unestablished counts, confirmed Stock value and completeness), applied `filters`, paginated `items`, and `pagination`.

Each item contains Product public UUID/name/unit/status, expected quantity, low-stock threshold, cost-basis state, optional valid average cost/value, and derived low/negative flags. Product detail links use the existing Stock workspace and its authorized Product detail read.

## 4. Customer Debt report

**Route:** `GET /api/v1/shop/reports/customer-debt`  
**Owner:** CustomerDebt  
**Required permissions:** `report.view`, `customer.view` AND `debt.view`  
**Source:** Existing `ReceivableBalanceQuery::projection()`, executed within `DebtReadSnapshot`, with the existing `DebtSchema` readiness gate.

### Query filters

| Field | Accepted values | Default |
|---|---|---|
| `status` | `ALL`, `OWING`, `OUTSTANDING`, `PARTIAL`, `SETTLED`, `OVERDUE` | `OWING` |
| `customer_status` | `ACTIVE`, `INACTIVE`, `ALL` | `ALL` |
| `search` | Literal Customer display name only, maximum 160 characters | empty |
| `page` | Integer 1–100000 | 1 |
| `per_page` | Integer 1–50 | 20 |

### Definitions

- Outstanding is derived from original Receivable amount, all effective signed adjustments and effective payment allocations, including linked reversals/compensations exactly once. It is never read from a mutable stored balance.
- Customer collections reduce Receivables; they are not Sales revenue.
- Settlement is derived as NO_DEBT (no Receivables in the current Shop projection), SETTLED (positive historical bill count and zero outstanding), PARTIAL (positive outstanding and positive effective collection), or OUTSTANDING.
- Overdue requires positive outstanding and an established `due_date` earlier than the server-derived Shop-local date. No due date and due today are not overdue.
- Customer-level totals are computed across all of that Customer's Receivables in the current Shop before Customer status filtering, matching the existing CustomerDebt semantics. The Customer directory remains Tenant-scoped as in the existing CustomerDebt query; no other Shop's Receivable amounts enter this report. Report list summary totals cover all matching Customers, not just the current page.
- Literal name search never searches private phone/notes. The report response contains no Customer phone, private notes, internal IDs, Audit metadata, COGS or profit.
- Contradictory balance evidence fails closed with `DEBT_EVIDENCE_INVALID` (409); it never becomes a zero placeholder.

### Response data

`shop` (timezone and currency), UTC `as_of`, Shop-local `shop_date` and `refreshed_at`, applied `filters`, filtered `summary` (customer/bill counts, outstanding/overdue amounts, overdue and partially-paid customer counts), paginated minimal Customer `items`, and `pagination`.

Customer items contain only public UUID, display name, active/inactive status, bill count, original/adjusted/collected/outstanding/overdue decimal strings, overdue bill count, next due date, derived settlement state and overdue flag. Permitted drill-down uses the existing CustomerDebt page and its own authorization.

## 5. Reconciliation and variance report

**Route:** `GET /api/v1/shop/reports/reconciliation`  
**Owner:** Reconciliation  
**Required permissions:** `report.view` AND `reconciliation.view`.  
**Additional field authority:** variance values/counts are returned only when the actor also has `reconciliation.review` and the existing `BlindReadPolicy` permits the corresponding Cash/Stock scope.

### Query filters

| Field | Accepted values | Default |
|---|---|---|
| `scope` | `ALL`, `CASH`, `STOCK`, `FULL` | `ALL` |
| `page` | Integer 1–100000 | 1 |
| `per_page` | Integer 1–50 | 20 |

### Definitions and privacy

- Read only `control_kind='STANDARD'` and `status='FINALIZED'` persisted sessions. Opening Cash baseline controls are not reported as standard checks.
- Never reconstruct a finalized result from current Sales, Stock, Debt or Financial Movement records.
- Session totals classify CASH, STOCK and FULL separately. A FULL session contributes once to the finalized session count; its Cash and Stock variance evidence remains in the separate respective variance families.
- Cash variance reads `reconciliation_variances.amount_variance` and preserves the stored sign: actual minus expected. The response returns a signed decimal string for each finalized session when authorized. Stock variance reads `reconciliation_variances.quantity_variance`. The UI explains a negative amount as a shortage and a positive amount as more cash counted than expected.
- Stock quantities use Product-specific units. The report counts Product shortage and surplus variance rows; it does not add unlike units into one misleading quantity.
- Variance summaries count sessions/items; Cash amounts are not summed across historical sessions because later controls use the prior finalized actual as their new baseline.
- Without `reconciliation.review`, or while the existing blind-count policy blocks a scope, the corresponding variance values/counts are omitted. Expected amounts/quantities, blind values, review reasons, abort reasons, note text, Device details and other private evidence are never serialized.
- Finalized detail links resolve to the existing Cash, Stock or Full control view, which applies its own source and review rules.

### Response data

`shop` (timezone and currency), UTC `as_of`, Shop-local `refreshed_at`, applied scope filter, `variance_visibility` flags, `summary` (finalized total, CASH/STOCK/FULL session counts, latest finalized time and only authorized variance summary fields), paginated `items`, and `pagination`.

Each item contains public session UUID, scope, fixed cutoff and finalization instants, a server-generated existing detail hash, and only the variance fields permitted for that actor/scope.

## 6. Frontend and navigation contract

The existing Shop navigation exposes one **Shop reports** menu item only when the server-derived capability `view_operational_reports` is true. The capability requires `report.view` and at least one complete source permission set. Separate source capabilities control each section:

- `view_stock_report`: report.view + inventory.view.
- `view_customer_debt_report`: report.view + customer.view + debt.view.
- `view_reconciliation_report`: report.view + reconciliation.view.

These are display hints only; each API query independently rechecks its required permissions and scope. No permission is inferred from another.

The screen uses existing Vue Composition API, ES modules, Shop shell, shared UI primitives and Sass classes. It provides independent loading/error/empty states, bounded filters and pagination, source-detail navigation, refresh timestamps and responsive summary-first cards. An offline transition clears live report figures; nothing is persisted to localStorage/IndexedDB and no offline freshness is claimed. READ_ONLY permits authorized reads; BLOCKED/CLOSED navigation is already restricted by the shared registry and the API remains authoritative.

## 7. Schema, migration, cache and export disposition

No new table, column, materialized projection, index, migration, SQL twin, model, cache, queue, dependency or frontend framework is required by the inspected source contracts. Existing composite keys and source projections are used. Native EXPLAIN/query-plan and pilot-shaped timing evidence remain manual acceptance requirements; no performance result is claimed by this contract.

WP2 implements no export route or UI. Export remains excluded unless a later explicit Owner decision defines the exact report, fields, date/row limits, format, audience, authority and Audit policy. No export permission is presumed.

## 8. Manual acceptance mapping

- **P8-13:** current Stock quantities against Stock Position.
- **P8-14:** low-stock threshold derivation and threshold changes.
- **P8-15:** confirmed Stock value against quantity and VALID cost basis.
- **P8-16:** RETAINED/UNESTABLISHED cost states are not shown as confirmed value.
- **P8-17:** Intake, Sale, Return and reversal effects reflected once through current Stock Position.
- **P8-18:** current Stock is distinguished from historical Reconciliation snapshots.
- **P8-19:** Customer outstanding against Receivable evidence.
- **P8-20:** partial/settled/overdue derivation and Shop-local due-date boundary.
- **P8-21:** allocations, adjustments and compensations counted once.
- **P8-22:** Customer private fields remain masked.
- **P8-23:** finalized Cash variance sign and value.
- **P8-24:** finalized Stock/Full variance summary without unlike-unit addition or session double counting.

All twelve cases remain Not run until observed in the supported PHP 8.2+, CodeIgniter, Shield and MySQL 8.4 environment with the existing evidence standard. Static contracts are not native acceptance.
