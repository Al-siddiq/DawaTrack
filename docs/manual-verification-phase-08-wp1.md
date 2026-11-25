# Phase 8 WP1 — native manual verification guide

**Scope:** Sales after returns, Cash/Transfer/Credit mix and permission-gated Gross Profit.  
**Environment:** Isolated test Shop with synthetic data; PHP 8.2+, CodeIgniter 4, Shield and MySQL 8.4 LTS. Do not use live Shop balances or real customer data.  
**Result rule:** Record actual outcomes only. A case not executed remains Not run or Blocked.

## Source-level contract check

Run `php tests/contracts/p8-wp1.php` from the repository root. This is a source-level guard for permission checks, date boundaries, source calculations, plain-English labels and read-only behavior. It does not execute MySQL, CodeIgniter routing or browser behavior and does not replace any manual case below.

## Required setup

Create synthetic posted Sales in one Shop with known line totals, historical unit-cost snapshots, Cash-only, Transfer-only, mixed Cash/Transfer and Credit compositions. Include a customer Receivable with a due date. Add partial and full Returns, a Sale compensation, a Return compensation, and a debt collection on a separate credit Sale. Create a Shop expense to prove it is not subtracted from Gross Profit. Set the Shop timezone to `Africa/Lagos`.

Prepare these Staff access profiles using the existing Staff permission screen:
- Staff A: `report.view` only.
- Staff B: `sale.view` only.
- Staff C: `report.view` + `sale.view`, without `sale.profit.view`.
- Staff D: `report.view` + `sale.view` + `sale.profit.view`.
- Primary Owner: existing role-derived authority.

Record the exact IDs, amounts, posted_at instants, source links and grants in the fixture sheet before running. Do not assume an example in the integration contract is real test data.

## Cases

### P8-01 — Today report
1. Post one known Sale today in Shop time.
2. Open Shop → Sales → Sales report; choose Today.
3. Compare Sales after returns and the payment split with the source Sale and payment records.
4. Expected: only posted records in today's Shop-local date are included; drafts are excluded; dates and last refresh are shown.

### P8-02 — This month report
1. Prepare posted Sales/Returns on the first day of the month and today.
2. Choose This month.
3. Compare the total with the two source periods.
4. Expected: the current month start through today's Shop date is included, without including future dates.

### P8-03 — Custom date and boundary
1. Prepare records immediately before the selected local start, exactly at the start, immediately before the next-day exclusive end, and exactly at the next day's start.
2. Choose Custom dates for the test range.
3. Expected: start and end Shop dates are inclusive; the next Shop date is excluded; adjacent periods do not double count. Verify UTC conversion against Africa/Lagos.

### P8-04 — Cash-only Sales
1. Post a Sale paid fully in Cash.
2. Select the period containing its posted_at.
3. Expected: Cash after refunds equals the Sale payment; Transfer and Credit are zero.

### P8-05 — Transfer-only and mixed Sales
1. Post a Transfer-only Sale and a mixed Cash/Transfer Sale.
2. Expected: each channel equals its authoritative payment rows and the two channels together equal the paid portion of Sales.

### P8-06 — Credit Sales
1. Post a Sale with a customer and an unpaid balance/Receivable.
2. Expected: Money customers can pay later equals the Sale's original credit evidence. No Customer phone or private notes are exposed.

### P8-07 — Returns and compensation
1. Post a partial Return, then a full Return in a separate fixture, and a compensation for an eligible Return.
2. Expected: posted Returns reduce Sales and the matching Cash/Transfer/Credit component; a compensation reverses only its linked source effect; no row is counted twice.

### P8-08 — Debt collection is not new Sales
1. Post a Credit Sale, then collect part or all of the old Receivable in a later period.
2. Compare the Sales report before and after collection.
3. Expected: collection does not increase Sales, Cash/Transfer Sales mix or Credit Sales for the later collection period.

### P8-09 — Historical cost snapshot
1. Post a Sale with an established historical cost; later change current Stock cost through supported Inventory operations.
2. Reopen the original Sales period.
3. Expected: Gross Profit remains based on the Sale Item's historical cost snapshot, not today's Stock cost.

### P8-10 — Expenses remain separate
1. Record a Shop expense in the selected period.
2. Compare Gross Profit before and after the expense.
3. Expected: Gross Profit is unchanged by the expense and the screen does not label it Net Profit or subtract expenses.

### P8-11 — Profit permission masking
1. Sign in as Staff A with report.view only. Open Shop → Sales.
2. Expected: the report opens directly; no Sales-history request is made; the Sell destination and draft routes are not available. Try a direct saved-sale URL and a direct draft URL; both remain unavailable and do not show receipt/draft details.
3. Sign in as Staff B with sale.view only. Expected: Sales and Returns history and the Sell destination are available, but the Sales report tab is not shown. A direct report hash returns to Sales history.
4. Sign in as Staff C with report.view + sale.view but without sale.profit.view. Inspect the screen and report response.
5. Expected: Sales/payment totals are visible; the screen says “Money left after paying for goods” is not available for this Staff member; gross_profit is null and no COGS/profit values are returned. Staff D and Owner can see Gross Profit. Revoking sale.profit.view hides the figure; revoking report.view or sale.view removes that corresponding view on the next access refresh.

### P8-12 — Range, pagination, empty/error and device checks
1. Try invalid dates, reversed dates, a range longer than 366 days and unsupported period.
2. Try a Shop with no posted Sales.
3. Repeat on phone, tablet and desktop; test keyboard focus and screen-reader labels.
4. Expected: invalid requests return safe validation errors; empty periods show valid zero totals; no source evidence leaks; the layout remains readable; Shop dates/timezone/last refresh are clear; no report data is presented as offline-current. The aggregate report has no unbounded detail list or pagination; when the user has sale.view, the existing Sales/Returns history pagination continues to work.

## Evidence to retain

For every case record: case ID, exact commit, environment versions, synthetic fixture IDs, Shop timezone, actor role/grants, request/response evidence with private values removed, expected and actual amounts, browser/device, result, defect ID if any, reviewer and Africa/Lagos observation time. Do not copy production customer information or secrets into the register.
