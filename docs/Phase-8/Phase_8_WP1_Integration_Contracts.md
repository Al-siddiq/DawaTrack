# Phase 8 WP1 — Sales reporting integration contract

**Status:** Implementation candidate for Owner review.  
**Package:** Sales, Payment Mix and Gross Profit Reports.  
**Source baseline:** main `f5b815662807d96f75c257b62de0ccd7cbb98111`; implementation branch `feature/phase-8-wp1-sales-reporting`.  
**No schema migration or new dependency is introduced by this package.**

## 1. Scope and ownership

Sales owns the meaning and read interpretation of Sale, Return, payment and historical Sale Item cost evidence. `SalesReportQuery` is a read-only Sales infrastructure query. `SalesReportController` maps the HTTP request to the query and canonical response. No other module writes or owns report data.

The report is authorized by the existing `report.view` permission and current Shop context. Historical Gross Profit and COGS are included only when the actor also has `sale.profit.view`. A report grant does not imply Sale history access; `sale.view` remains independently required by the existing Sales history query.

## 2. HTTP contract

**Route:** `GET /api/v1/shop/reports/sales`

**Query parameters**

| Parameter | Type | Default | Rule |
|---|---|---|---|
| `from` | `YYYY-MM-DD` | First day of current month in Shop timezone | Real calendar date |
| `to` | `YYYY-MM-DD` | Current date in Shop timezone | Must be same as/after `from` |
| `group_by` | `DAY` or `MONTH` | `DAY` | Controls series grouping only |

Maximum range is 366 inclusive Shop calendar days. The Shop's `timezone_name` and `currency_code` are read from the server-owned Shop row. No Tenant/Shop identifiers are accepted from the browser.

**Success envelope:** standard DawaTrack `ApiResponse`, with `data` containing:

- `period`: `from`, `to`, `group_by`, `timezone_name`.
- `currency_code`.
- `profit_visible`.
- `summary`: sales added, returns/cancellations, net Sales, Cash and Transfer receipts/refunds, Credit created/adjusted, derived payment-mix check, and (only when authorized) net COGS and Gross Profit.
- `series`: one row per Shop-local day or month, with the same report measures.
- `refreshed_at`: server UTC time.
- `profit_explanation` and `profit_cost_note` only when profit is authorized.

**Safe failures:** standard error envelope; invalid dates/grouping and ranges return validation errors. BLOCKED/CLOSED and missing `report.view` are denied by the established CommercialAccess/AccessGate behavior. Responses are `Cache-Control: no-store, private`.

## 3. Source-of-truth and sign rules

| Report value | Authoritative source | Rule |
|---|---|---|
| Sales added | `sales` | Posted rows with `kind='SALE'`, by `posted_at` |
| Returns/cancellations | `sales` | Posted rows with `kind='RETURN'`, by `posted_at` |
| Net Sales | Sale rows above | Sales added less Returns/cancellations |
| Cash/Transfer received/refunded | `sale_payments` joined to posted `sales` | `direction=RECEIPT` is received; `REFUND` is refunded; channel remains Cash or Transfer |
| Credit created | `sales.credit_created_amount_snapshot` | Posted Sale snapshot; Credit is not a payment row |
| Credit adjustments | `receivable_adjustments.amount_delta` joined through its Sale | Positive and negative effects remain separate |
| Cost of products sold | `sale_items.line_cogs_amount` | Historical posted snapshot; signed by Sale/Return kind |
| Gross Profit | Derived from the above | Net Sales less net historical cost of products sold; expenses are not deducted |

The existing compensation workflow creates a posted compensating row with the opposite `kind` and opposite payment/Receivable effects. Therefore the signed effect is represented by the posted row's persisted `kind`, payment `direction` and signed adjustment; the report does not infer compensation from browser state or count both effects as positive.

Debt collection is not joined into this report as new revenue. It reduces a Receivable, not Sales. Financial Movement expenses are not included in WP1 totals and no net-profit result is calculated.

## 4. Shop-local date boundaries

The query reads the configured IANA timezone from `shops.timezone_name`. PHP `DateTimeZone` creates each Shop-local midnight and the next local midnight; each interval is converted to UTC and used as a half-open `posted_at >= start AND posted_at < end` range. This avoids relying on MySQL timezone tables and prevents adjacent dates from double counting.

All five report aggregates use bounded, indexed Shop/Tenant-scoped queries over at most 366 generated daily buckets. Monthly results are summed from those daily aggregates using exact decimal-string helpers.

## 5. Exact money and privacy

- SQL aggregates use DECIMAL source columns.
- PHP calculations use the existing `SaleMoney` decimal-string helpers; no binary float is used for calculation.
- The COGS query is not executed when `sale.profit.view` is absent.
- COGS and Gross Profit keys are omitted from the response when unauthorized; the UI shows a simple access explanation.
- Customer phone, notes, private data, internal database IDs, cost details when unauthorized, Audit reasons and secrets are not returned.
- No report response is persisted or cached.

## 6. UI and plain-English contract

The existing Sales workspace remains in the existing Menu group. Its navigation capability is visible to either `sale.view` or `report.view`; the server still independently protects each API. A report-only Staff member sees the Sales summary but does not receive Sales history access. A user with both permissions can move between history and summary.

Approved labels include:

- “Sales before returns”
- “Returns and cancelled sales”
- “Sales after returns”
- “Cash received” / “Cash refunded”
- “Transfer received” / “Transfer refunded”
- “Credit added to customer bills”
- “Credit added back to customer bills”
- “Credit removed from customer bills”
- “Gross profit (before shop expenses)”

The page explains that Credit is the part of a Sale left on a Customer bill, not money received. It explains that Gross Profit is Sales after Returns minus saved product costs and that Shop expenses have not been removed. It does not call Gross Profit “net profit”.

The report is read-only and online-authoritative. It has date filters, daily/monthly grouping, loading/error/empty/access states and a last-updated time in the Shop timezone. It does not persist offline report data.

## 7. Query and migration decision

No migration, SQL twin, index, table, model, cache, dependency or new permission is added. Existing Sales indexes are used. Native `EXPLAIN`/`EXPLAIN ANALYZE` and representative-data performance checks remain required manual acceptance evidence; they are not claimed by this contract.

## 8. Manual verification IDs

P8-01 through P8-12 remain the controlling WP1 test identities. See [WP1 manual result register](Phase_8_WP1_Manual_Results.csv). A case remains Not run until observed in the supported native environment and recorded with the repository evidence fields.
