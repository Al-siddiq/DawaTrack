# Phase 8 WP1 — Sales report integration contract

**Status:** Implemented on the WP1 feature branch; native database/browser verification and Owner package acceptance remain pending.  
**Scope:** Read-only Sales period summary, payment mix and permission-gated Gross Profit.  
**Owner modules:** Sales (report definition and orchestration), Inventory (historical cost-state reconstruction), Identity (existing grants), TenantShop (current Shop context).

## 1. API

`GET /api/v1/shop/reports/sales`

Query parameters:

| Field | Allowed values | Meaning |
|---|---|---|
| `period` | `TODAY`, `THIS_MONTH`, `CUSTOM` | Today, the current Shop month through today, or a selected date range |
| `from_date` | `YYYY-MM-DD` | Required only for `CUSTOM` |
| `to_date` | `YYYY-MM-DD` | Required only for `CUSTOM` |

The default is `TODAY`. Custom ranges are inclusive Shop calendar dates and may cover at most 366 calendar days. A reversed range, invalid calendar date, unsupported period or dates supplied for a non-custom period are rejected with the standard safe validation envelope.

The service reads `shops.timezone_name`, converts the selected Shop-local midnight boundaries to UTC, and filters authoritative `posted_at` instants using a half-open interval: start inclusive, next-day-after-end exclusive. The response returns the selected dates, Shop timezone and UTC refresh instant. It does not use browser time to define the report period.

Success uses the established API envelope and returns:

```json
{
  "success": true,
  "data": {
    "period": {
      "type": "TODAY",
      "from_date": "2026-11-24",
      "to_date": "2026-11-24",
      "timezone": "Africa/Lagos",
      "refreshed_at": "2026-11-24 19:00:00.000000"
    },
    "sales": {
      "amount": "12500.00",
      "sale_count": 4,
      "return_count": 1,
      "cancellation_count": 0
    },
    "payments": {
      "cash_amount": "5000.00",
      "transfer_amount": "4500.00",
      "credit_amount": "3000.00"
    },
    "gross_profit": {
      "amount": "4200.00",
      "provisional": false,
      "label": "Money left after paying for goods",
      "message": "This is what remained after the cost of the goods sold. Shop expenses have not been taken out."
    },
    "profit_visible": true
  },
  "meta": { "request_id": "..." },
  "error": null
}
```

The amounts above illustrate the response shape only; they are not fixture or production results. If the current user lacks `sale.profit.view`, `gross_profit` is `null`, `profit_visible` is `false`, and no COGS/profit amount is returned.

## 2. Authoritative calculation rules

### Sales after returns

Only posted `sales` rows whose `posted_at` falls inside the selected period are included. Drafts are excluded. The signed effect is:

- ordinary posted Sale: positive;
- ordinary posted Return: negative;
- compensation of a Sale: negative;
- compensation of a Return: positive.

This uses the original record's kind for a compensation. Original and compensation rows therefore cancel each other exactly when a record is reversed. A normal Return reduces Sales in the period it is posted; later debt collection is not Sales.

The report also counts posted Sale records, Return records and compensation records separately. These are record counts, not a claim that every counted Sale remains uncancelled.

### Cash and Bank Transfer

Read `sale_payments` linked to posted Sales/Returns/compensations in the same Shop and period. For each channel, RECEIPT is positive and REFUND is negative. The payment direction must also agree with its linked Sale or Return. Payment reversal rows therefore offset their source row. No current Cash balance or client-provided total is used.

### Credit

Credit is the original amount of posted Sale Receivables created in the period plus sale-owned Receivable adjustments posted in the period (`RETURN_REDUCTION`, Sale-cancellation `CORRECTION_DECREASE`, and the corresponding `REVERSAL`). Generic bill corrections have no Sales source and are excluded. Debt payment allocations/collections are excluded: collecting an old bill is not a new Sale.

The service checks each posted Sale, Return and compensation row to confirm that its saved item lines add up to that record's header total. It then verifies with MySQL DECIMAL arithmetic that Cash + Transfer + Credit equals Sales after returns and that the signed item total equals the signed header total. An inconsistent source set fails safely with `COMMERCIAL_EVIDENCE_INVALID`; the report does not silently display a plausible but unbalanced total.

### Gross Profit

Only when `sale.profit.view` is granted, the service sums signed `sale_items.line_sales_amount - sale_items.line_cogs_amount`. The line COGS amount is the historical snapshot recorded with the Sale/Return; today's Stock cost is never substituted. Return and compensation signs follow their source operation.

The existing Inventory `CommercialCostHistory` and `CostStateReconstructor` are used to detect an `UNESTABLISHED` historical cost basis. If any included source Sale item has that state, the result is marked provisional and the UI explains that it is an estimate. Original-item links are followed through Return and Return-compensation lines to the original Sale Item before checking its historical Stock movement. A missing source movement or cost link fails safely.

The screen calls this **Money left after paying for goods** and explains that it is Gross Profit before Shop bills and other expenses. Phase 8 WP1 does not calculate net profit or subtract Financial Movement expenses.

## 3. Authorization and roles

- Every request requires the existing `report.view` grant through `CommercialAccess`, current Tenant/Shop context and AccessGate.
- Primary Owner authority follows the existing role-derived known-permission policy.
- Staff require an active explicit `report.view` grant. Existing grants are not expanded.
- `sale.profit.view` independently gates every Gross Profit/COGS amount. `report.view` does not imply it.
- Existing `sale.view` independently controls the Sales and Returns history/detail view. A Staff member with only `report.view` sees the Sales report and never triggers the Sales history API.
- The existing Shop workspace capability `view_sales_workspace` is presentation metadata derived from `sale.view OR report.view`; it is not a new permission and never replaces server authorization. The shared template's legacy `view_sale_drafts` mount hint uses the same union only to mount the Sales workspace. The `Sell` destination is separately gated by `view_sale_entry`, derived only from `sale.view`, so report-only Staff cannot open draft Sales. Sales APIs continue to require `sale.view`.
- READ_ONLY allows authorized report reads. BLOCKED/CLOSED and inactive/foreign Shop context remain denied by existing access rules.

No new permission code is introduced. `report.view` and `sale.profit.view` already exist in the closed permission catalogue.

## 4. UI and plain-English rules

The report is reached through the existing Shop **Sales** workspace. Users who have both permissions can switch between **Sales report** and **Sales and returns**. A report-only Staff member is taken directly to the report. A Sales-only Staff member sees the existing Sales/Returns history and no report tab.

The screen uses short explanations:

- **Sales after returns:** Sales minus returns and cancelled sales.
- **Cash after refunds:** Cash received, less Cash paid back to customers.
- **Bank transfers after refunds:** Transfers received, less transfers paid back.
- **Credit after returns:** Money customers still needed to pay when goods were sold, less credit removed by returns.
- **Gross profit before shop expenses:** What remained after the cost of goods sold; Shop expenses have not been taken out.

The page shows the Shop dates, Shop timezone and last refresh time. It does not use charts that could obscure the amounts. No local report cache or offline freshness claim is introduced. Source-detail navigation is shown only when the user also has `sale.view`.

## 5. Database and performance boundary

No table, column, migration, SQL twin, index, dependency, reporting snapshot or warehouse is added. Existing Sales, Sale Item, Sale Payment, Receivable, Receivable Adjustment and Stock Movement evidence is read inside one repeatable-read, read-only snapshot.

The existing Sales posted index and owning evidence indexes are used. Native `EXPLAIN`/representative-volume performance review remains a required manual gate; no index is added without measured evidence. Gross Profit historical-state checking is bounded to 25,000 distinct item movement targets per request; if exceeded, the API asks the user to choose a shorter date range.

## 6. Failure behavior

- Invalid dates/period/range: safe `422` validation response.
- Missing/invalid Shop timezone: safe `503` response.
- Access denied or Shop BLOCKED/CLOSED: existing access error.
- Mismatched Sales header/item/payment/credit evidence: safe `409 COMMERCIAL_EVIDENCE_INVALID`.
- Too many historical item movement targets for Gross Profit state checking: safe `422 REPORT_RANGE_TOO_WIDE`.
- No matching posted Sales: valid zero amounts and zero counts.
- Request is private and no-store through the existing `CommercialController` response boundary.

No report read writes Audit events, business rows, change sequence entries or synchronization state.
