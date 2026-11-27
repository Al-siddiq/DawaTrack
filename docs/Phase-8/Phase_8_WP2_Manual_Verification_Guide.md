# Phase 8 WP2 — Native Manual Verification Guide

**Status:** All cases P8-13–P8-24 are Not run until the Owner records actual observations in `Phase_8_WP2_Manual_Results.csv`.  
**Environment:** Supported PHP 8.2+, CodeIgniter 4, Shield and MySQL 8.4 LTS. Use a disposable/synthetic Shop for tests that change stock, receivables or reconciliation evidence. Do not use production records or secrets.

## 1. Preserve the local working tree and obtain the candidate

The implementation branch is cumulative on the Owner-accepted WP1 source candidate:

`feature/phase-8-wp2-stock-debt-reconciliation`

Before switching branches, preserve every local modification and untracked file. Do not use `git reset --hard`, `git clean` or discard generated assets/log files. If the current working tree is still dirty, create a backup or stash including untracked files before switching.

After preservation:

```bash
git fetch origin
git switch --track origin/feature/phase-8-wp2-stock-debt-reconciliation
git status --short
```

Confirm that the WP2 branch is checked out and that the working tree contains no unexpected modifications.

## 2. Static, syntax and build checks

Run from the repository root:

```bash
php -l app/Modules/Inventory/Infrastructure/StockReportQuery.php
php -l app/Modules/CustomerDebt/Infrastructure/CustomerDebtReportQuery.php
php -l app/Modules/Reconciliation/Infrastructure/ReconciliationReportQuery.php
php -l app/Controllers/Api/OperationalReportController.php
php -l app/Controllers/Api/ShopController.php
php -l app/Config/Routes.php

node --check resources/js/components/operational-reports-panel.js
node --check resources/js/components/products-panel.js
node --check resources/js/shop/app.js

php tests/contracts/wp8-wp2.php
node tests/contracts/wp8-wp2-ui.mjs

npm run build

php spark routes | findstr /I "shop/reports"
```

The build must use the existing locked frontend pipeline. Do not hand-edit files under `public/assets/`; `npm run build` generates them from `resources/`.

Expected registered routes:

- `GET api/v1/shop/reports/stock`
- `GET api/v1/shop/reports/customer-debt`
- `GET api/v1/shop/reports/reconciliation`

Retain the complete command output. Static contract PASS is not native acceptance.

## 3. Actor and permission fixtures

Use existing synthetic Shop/member fixtures and the project's permission provisioning flow. Do not add permissions or silently expand Staff grants.

Record each actor's effective grants before testing:

1. Primary Owner with existing Shop permissions.
2. Staff with `report.view` + `inventory.view`, but without CustomerDebt or Reconciliation grants.
3. Staff with `report.view` + `customer.view` + `debt.view`, but without `inventory.view` and `reconciliation.view`.
4. Staff with `report.view` + `reconciliation.view`, but without `reconciliation.review`.
5. Staff with `report.view` + `reconciliation.view` + `reconciliation.review`.
6. Staff missing `report.view`, even if they have one or more source-domain grants.

Verify the navigation is only a presentation hint: call each endpoint directly as each actor. Each query must enforce its own source permissions. A report grant alone must not reveal source data. No test actor should receive new grants merely to make a report appear.

## 4. API, scope and response checks

For each route, verify:

- unauthenticated request is denied;
- authorized request resolves the active Shop from the server session/host, not client-supplied IDs;
- foreign Tenant/Shop IDs are not accepted as authority;
- missing source permission returns the existing safe permission denial;
- READ_ONLY permits authorized GET reads;
- BLOCKED/CLOSED restrictions remain effective;
- invalid filters and unsupported query keys return the canonical safe 422 envelope;
- response contains request ID and `Cache-Control: no-store, private`;
- no response includes internal numeric IDs, private Customer fields, review reasons, blind expected values or unrelated Shop records;
- pagination is capped at 50 and ordering is deterministic;
- no read creates Audit noise or mutates source evidence.

Try unsupported `tenant_id`, `shop_id`, `customer_id` and `actor_id` query keys. They must be rejected rather than used for scope.

## 5. P8-13 — Current Stock levels

1. Select a synthetic Shop and an authorized Owner/Staff actor with `report.view` + `inventory.view`.
2. Compare several Products shown by the Stock report with the existing Products/Stock detail and current `stock_positions.expected_quantity`.
3. Include a normal positive quantity, zero quantity and negative quantity if the fixture supports them.
4. Confirm no quantities from different units are summed into one total.
5. Confirm Product detail links open the existing authorized Product detail and return to the Stock list correctly.

Record expected and actual quantities, units, Shop, actor and evidence.

## 6. P8-14 — Low-stock threshold

1. Choose a Product with a known `low_stock_threshold`.
2. Compare `expected_quantity <= low_stock_threshold` with the report's Low stock state.
3. Include a Product exactly at threshold, below threshold and above threshold.
4. Through the existing authorized threshold workflow, change the threshold in the synthetic Shop.
5. Refresh the report and confirm the low-stock state changes immediately from the current persisted threshold; no second flag is stored.
6. Verify the report does not write or alter the Product threshold.

## 7. P8-15 — Confirmed Stock value

For each tested Product with positive quantity and `cost_basis_state='VALID'`:

1. Read the persisted quantity and average unit cost from the current Stock Position.
2. Calculate `quantity × average_unit_cost` using exact decimal arithmetic.
3. Round each Product position to two currency decimals using the established Inventory line-amount precision.
4. Sum the per-Product rounded values and compare with `summary.stock_value_confirmed`.
5. Compare each displayed Product Stock value with the same calculation.
6. Verify no PHP/JavaScript binary floating-point calculation is used.

Do not include RETAINED, UNESTABLISHED or negative positions in the confirmed Stock value.

## 8. P8-16 — RETAINED and UNESTABLISHED cost states

Prepare or locate synthetic Products in each cost state:

- `VALID` with positive quantity;
- `RETAINED` after the existing Inventory rules have retained the last valid basis at zero/negative quantity;
- `UNESTABLISHED` where the cost basis has not yet been established.

Verify the report displays each state distinctly, excludes RETAINED/UNESTABLISHED/negative positions from confirmed valuation, shows the completeness warning and does not display a false-precision per-Product value for excluded positions. Do not change the underlying cost state to make the report look complete.

## 9. P8-17 — Stock movement effects

Using synthetic evidence only, post one approved Intake, one Sale, one Return and one authorized reversal/compensation scenario using the existing workflows.

After each operation:

- compare the current Stock Position and Product detail with the report;
- verify each posted effect is reflected exactly once;
- confirm drafts do not change current Stock;
- confirm reversal/compensation effects follow the existing Inventory ledger/projection behavior;
- do not mutate historical Sale Item cost snapshots.

The report reads current Stock Position; it does not reconstruct or repair Stock from the browser.

## 10. P8-18 — Current Stock versus historical Reconciliation Stock

1. Finalize a synthetic Stock or Full check.
2. Record the check's captured expected Stock and finalized variance.
3. Perform a later legitimate Stock movement in the synthetic Shop.
4. Confirm the Stock report changes to the current position.
5. Confirm the Reconciliation report and existing finalized detail remain the historical cutoff result and do not change.
6. Confirm no report creates a Stock Adjustment or Reconciliation correction.

## 11. P8-19 — Customer outstanding balance

For a synthetic Customer, reconcile the report against the existing CustomerDebt statement and source evidence:

- original posted Receivable amounts;
- effective signed Receivable Adjustments;
- effective Debt Payment Allocations;
- linked payment reversals/compensations.

Compare bill count, original amount, adjustment amount, adjusted amount, collected amount and outstanding amount. Do not use a mutable customer balance or treat a collection as new Sales revenue.

## 12. P8-20 — Partial, settled and overdue states

Use Shop-local date boundaries:

1. Customer with a positive balance and due date before the Shop-local date: overdue.
2. Customer with a positive balance and due date equal to today: not overdue.
3. Customer with no due date: not overdue solely because of age.
4. Customer with effective partial allocation and remaining positive balance: PARTIAL.
5. Customer with zero outstanding after effective allocation: SETTLED.
6. Customer with no Receivables: NO_DEBT.

Test around midnight in the configured Shop timezone. Verify server time, not the browser clock, controls overdue classification.

## 13. P8-21 — Allocations, adjustments and compensations

Create synthetic cases with:

- one full allocation;
- one partial allocation;
- a signed adjustment;
- a linked payment reversal/compensation;
- a linked adjustment reversal/compensation.

Reconcile each result to the Receivable source details. Confirm each effective signed row is counted exactly once, reversed allocations do not remain collected, and collections never appear as new Sales revenue or Gross Profit.

## 14. P8-22 — Customer privacy

Call the Customer Debt endpoint as an authorized report viewer and inspect the complete JSON response, including every page and filter combination.

Verify it contains only public Customer UUID, display name, active/inactive status and the approved balance summary fields. It must not expose phone, private notes, addresses, private metadata, internal numeric IDs or unrelated Customer fields. Verify the existing Customer detail endpoint still applies its own separate authority.

## 15. P8-23 — Finalized Cash variance

Use a synthetic finalized Cash check with a known expected and actual amount.

1. Calculate actual minus expected using exact decimal arithmetic.
2. Verify a negative value is presented as Cash short; a positive value as more Cash counted than expected.
3. Verify the signed amount in the API is unchanged.
4. Verify the summary counts a non-zero Cash variance session once.
5. Verify Cash variance amounts are not added across multiple historical sessions.
6. Confirm the detail link opens the existing finalized Cash control and still enforces `reconciliation.review`.

## 16. P8-24 — Finalized Stock and Full variance

Use synthetic finalized Stock and Full checks with products that have different units.

1. Compare each Product's `quantity_variance` with actual minus expected.
2. Verify shortage and surplus item counts are separate.
3. Confirm the report does not add unlike Product units into one total quantity.
4. Confirm a Full session appears once in finalized session totals while Cash and Stock variance evidence remains in its respective family.
5. Perform a later legitimate Stock movement and prove finalized report results remain unchanged.
6. Confirm the detail link opens the existing finalized Stock/Full check and blind/review restrictions remain effective.

## 17. Query-plan, performance and browser checks

Capture native MySQL `EXPLAIN` output for the Stock Position, Receivable projection, finalized-session and variance queries using representative synthetic Shop cardinality. Record indexes used, estimated rows, actual timing where supported and dataset size. Do not add an index unless the plan allows it and measured evidence demonstrates a need; any approved index requires its paired CodeIgniter migration, SQL twin, register entry and rollback/preflight notes.

Browser checks:

- phone width around 360–390 px;
- tablet width around 768 px;
- desktop width around 1280–1440 px;
- keyboard-only navigation through filters, cards, pagination and detail links;
- focus visibility, accessible labels and meaningful empty/error/loading messages;
- independent section failures do not hide other authorized reports;
- offline transition clears report figures and shows the offline state;
- returning online refreshes from the server; no local/offline report persistence;
- READ_ONLY/BLOCKED/CLOSED presentation is consistent with the shared Shop shell.

## 18. Evidence and acceptance

For every P8-13–P8-24 row in `Phase_8_WP2_Manual_Results.csv`, record:

- environment/version;
- synthetic fixture and Shop;
- actor and exact grants;
- selected filter/scope;
- expected and actual result;
- evidence reference (sanitized screenshot, response capture, query output or test record);
- reviewer;
- Africa/Lagos observation time;
- Pass, Fail, Blocked or Not run.

Do not mark a case Pass based on a static contract, source inspection, mock, expected output or unexecuted command. Stop after WP2 manual verification and return the evidence for Owner review. Do not begin WP3 until explicit WP2 acceptance.
