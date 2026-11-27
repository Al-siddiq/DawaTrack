# Phase 8 WP3 — Native Manual Verification Guide

**Package:** WP3 — Shop Dashboard, Approved Export and Phase 8 Integration  
**Branch:** `feature/phase-8-wp3-shop-dashboard`  
**Predecessors:** WP1 and WP2 Owner-accepted source candidates.  
**Status:** P8-25–P8-37 remain Not run until actual observations are recorded.  
**Environment:** Supported PHP 8.2+, CodeIgniter 4, Shield and MySQL 8.4 LTS.

## 1. Preserve local work and obtain the WP3 candidate

Before switching branches, preserve every local change and untracked file. Do not use `git reset --hard`, `git clean` or discard generated files/logs.

```bash
git status --short
git stash push -u -m "Preserve local work before Phase 8 WP3 verification"
git fetch origin
git switch --track origin/feature/phase-8-wp3-shop-dashboard
git status --short
```

The branch is cumulative on the Owner-accepted WP2 source candidate. It includes WP1 and WP2 exactly once. It does not merge either package into `main`.

## 2. Syntax and static contract checks

Run from the repository root:

```bash
node --check resources/js/components/operational-dashboard-panel.js
node --check resources/js/components/workspace-home.js
node --check resources/js/components/sales-report-panel.js
node --check resources/js/components/sale-history-panel.js

php -l app/Config/Filters.php
php -l app/Filters/SensitiveRateLimitFilter.php
php -l tests/contracts/wp8-wp3.php

php tests/contracts/wp8-wp3.php
node tests/contracts/wp8-wp3-ui.mjs

npm run build

php spark routes | findstr /I "shop/reports"
```

The route list must retain the four existing WP1/WP2 report GET routes. WP3 adds no dashboard route, export route or export permission. The frontend build generates the Shop renderer and copies source assets. Do not hand-edit generated `resources/js/shop/render.js` or `public/assets/`.

Retain the complete command output. Static PASS and a successful asset build are not native acceptance.

## 3. Test fixtures and actor matrix

Use the same synthetic Shop and evidence fixtures used for accepted WP1/WP2 testing. Do not use production records or secrets.

Record the effective permissions of these actors before testing:

1. Primary Owner with normal Shop authority and `report.view`.
2. Staff with `report.view` and `inventory.view` only.
3. Staff with `report.view`, `customer.view` and `debt.view` only.
4. Staff with `report.view` and `reconciliation.view`, without `reconciliation.review`.
5. Staff with `report.view`, `reconciliation.view` and `reconciliation.review`.
6. Staff with `sale.view` but without `report.view`.
7. Staff with source-domain grants but without `report.view`.
8. Staff without any report/source grants.

The dashboard is shown only when `report.view` is active. Each card is independently gated by its existing source capability. Gross Profit is only shown when the Sales API returns `profit_visible=true`; Reconciliation variance summaries are only shown when the response's `variance_visibility` permits them. Do not add Staff grants to make a card appear.

## 4. P8-25 — Sales and Gross Profit cards equal WP1

1. Sign in as an actor with `report.view`.
2. Open Shop Home. Record the dashboard's Sales date range and Shop timezone.
3. Compare Sales before returns, returns/cancellations, Sales after returns, Cash received, Transfer received and Credit created with the WP1 Sales report for exactly the same range.
4. If the actor has `sale.profit.view`, compare Gross Profit with the WP1 report's Gross Profit. It must remain Gross Profit before expenses.
5. If the actor lacks `sale.profit.view`, verify no Gross Profit or COGS value is returned/rendered; the dashboard must show the access explanation instead.
6. Change the date range and apply it. Confirm only the Sales card refreshes and its totals match WP1.
7. Open the Sales report from the dashboard. Confirm the selected `from_date` and `to_date` are carried in the `#sales-reports` hash and the Sales report opens with `period=CUSTOM` and the same dates.

Do not compare Gross Profit with Net Profit or deduct Financial Movement expenses.

## 5. P8-26 — Stock, Customer Debt and Reconciliation cards equal WP2

**WP3 correction note:** The initial Reconciliation dashboard error was traced to extra bind values in the finalized-session list query. The variance subquery and outer Shop scope now receive only the parameters matching their SQL placeholders. Re-run the Reconciliation card with an Owner/reviewer account and with a non-reviewer account; verify the summary loads in both cases and that variance fields remain masked for the non-reviewer. Do not mark this case Pass until the local database confirms it.

For each card the actor is authorized to see:

- **Current Stock:** compare Product count, low-stock count, negative-stock count, confirmed Stock value and valuation completeness with the WP2 Stock report. Do not add quantities across different Product units.
- **Current customer balances:** compare owing Customer count, outstanding amount, overdue amount, overdue Customer count and partly-paid Customer count with the WP2 Customer Debt report using the same Shop date.
- **Finished shop checks:** compare finalized total, Cash/Stock/Full counts, latest finalized time and any authorized variance counts with the WP2 Reconciliation report.

Confirm the dashboard does not calculate these values independently. Each card must equal the corresponding source report response.

## 6. P8-27 — Selected period, Shop timezone and drill-down

1. Choose a custom Sales period within the WP1 366-day limit.
2. Confirm the Sales card shows the selected start/end dates and configured Shop timezone.
3. Open the Sales report from the card. Confirm its date inputs and report result use the same selected dates and timezone.
4. Confirm every card shows its own source refresh time and Shop timezone.
5. Confirm Stock and Customer Debt are labelled as current positions, not historical totals for the Sales period.
6. Confirm Reconciliation is labelled as finalized evidence/latest finished checks, not a reconstruction for the Sales period.

WP2 APIs do not accept a historical period filter. The dashboard must not imply that the selected Sales period applies to current Stock, current Debt or finalized Reconciliation.

## 7. P8-28 — Tenant and Shop isolation

1. Capture an authorized request for each report route from the dashboard.
2. Attempt to add or substitute `tenant_id`, `shop_id`, `actor_id` and foreign identifiers.
3. Confirm unsupported filters are rejected or have no authority, and the server-resolved ShopContext remains decisive.
4. Sign into another synthetic Shop and confirm no figures from the first Shop appear.
5. Confirm no internal database ID or private Customer data is exposed in dashboard payloads.

## 8. P8-29 — No report.view

1. Sign in as Staff with `sale.view` but without `report.view`. Home should retain normal permitted shortcuts, but no dashboard report figures or Sales report access should appear.
2. Sign in as Staff with one or more source-domain grants but without `report.view`. The dashboard must not request report APIs.
3. Call each report endpoint directly as these actors. The server must deny access according to the existing report/source permissions.
4. Confirm hiding a card in the UI is not treated as the security control; the API must independently deny it.

## 9. P8-30 — READ_ONLY, BLOCKED and CLOSED

- READ_ONLY: an authorized user can load and refresh the dashboard because all four report APIs are read-only GETs. No dashboard mutation control is introduced.
- BLOCKED/CLOSED: the dashboard must not send report requests and must show the existing access restriction. Home's permitted access message remains intact.
- Confirm the shared Shop shell and AccessGate remain authoritative; the dashboard must not override access mode.

## 10. P8-31 — Bounded requests and request-storm prevention

Use browser developer tools with Network preserve log enabled.

1. Load Home once. Count requests: exactly one request per authorized report family (Sales, Stock, Customer Debt, Reconciliation); unauthorized families must not be requested.
2. Confirm the three WP2 summary requests use the supported `per_page=20&page=1` pagination values.
3. Confirm Sales uses the WP1 maximum range and never exceeds 366 inclusive Shop-local days.
4. Confirm typing in the date fields does not send requests. Only Update Sales period submits a new Sales request.
5. Confirm a Sales period change does not reload Stock, Debt or Reconciliation.
6. Confirm Refresh overview triggers at most one request per authorized report family.
7. Confirm leaving Home aborts in-flight dashboard requests and returning does not create duplicate concurrent requests.
8. Confirm there is no polling timer or automatic repeated request loop.
9. Confirm all four report GET routes share the existing per-user limit of 20 requests per 60 seconds. When exceeded, verify the canonical 429 `RATE_LIMITED` response and `Retry-After` header; do not mistake throttling for a source-report failure.

## 11. P8-32 — Query plans and pilot-shaped performance

Use the same pilot-shaped dataset and Shop used for WP1/WP2. Capture MySQL `EXPLAIN` (and `EXPLAIN ANALYZE` where supported) for each existing source report query path. Record table cardinalities, indexes used, estimated/actual rows and elapsed time.

Measure the four report requests individually and the Home dashboard's total initial load. Confirm no N+1 requests or duplicate report queries. Do not add a cache or index just to improve the dashboard. Any proposed index requires measured justification, Owner approval where required, a paired CodeIgniter migration and SQL twin, migration register entry and rollback/preflight notes.

## 12. P8-33 — Cache, freshness and source versions

1. Inspect each report response for `Cache-Control: no-store, private`.
2. Confirm no dashboard response cache, localStorage, IndexedDB, service-worker report cache or shared cache was added.
3. Confirm each card displays its own `as_of`/`refreshed_at` freshness marker and Shop timezone.
4. Confirm the UI does not claim the four independently read sources form one atomic snapshot.
5. Refresh and confirm each visible card re-reads its existing source report.
6. Confirm a changed Sales period refreshes only Sales and preserves current Stock/Debt and finalized Reconciliation values until their own refresh.

## 13. P8-34 — Export disposition

Export has not been approved and is not implemented in this candidate.

Verify:

- no export action or download control appears on Home or report pages;
- no dashboard export route or file endpoint is registered;
- no export permission is added to the permission catalogue or Staff grant UI;
- no export file is generated, cached or written to a public path;
- no export Audit event is emitted.

Do not interpret this as a permanent prohibition. A future export requires a separate explicit Owner decision defining report, fields, date/row bounds, format, audience, authority and Audit policy before implementation.

## 14. P8-35 — Loading, empty, error, stale, offline and access modes

1. Use browser throttling to observe loading indicators.
2. Use a synthetic Shop with no Sales/Stock/Debt/Reconciliation records and verify meaningful zero/empty states.
3. Cause one source API to fail while leaving the others available. Confirm each card fails independently and successful cards remain visible.
4. Load successful figures, then simulate an online network failure. Confirm any retained in-memory result is clearly labelled stale and cannot be mistaken for current.
5. Switch offline. Confirm live report figures are cleared and the offline message says no report figures are saved on the device.
6. Return online. Confirm the visible reports are fetched again from the server.
7. Confirm READ_ONLY is viewable and BLOCKED/CLOSED do not issue report calls.

## 15. P8-36 — Phone, tablet, desktop and accessibility

Check at approximately 360–390 px, 768 px and 1280–1440 px:

- cards reflow without horizontal page scrolling;
- headings, metric labels, amounts and timestamps remain readable;
- Sales date controls fit the screen and have programmatic labels;
- card actions have meaningful text and keyboard focus is visible;
- Tab/Shift+Tab reaches date fields, refresh and drill-down actions in a logical order;
- Enter submits the Sales period form and activates buttons;
- errors and offline/loading states are announced through the existing UI primitives;
- metrics are textual and do not depend on colour or chart graphics;
- currency, period, timezone and freshness are available to screen readers;
- zoom to 200% does not hide actions or important values.

## 16. P8-37 — Home section order

1. Sign in as the Shop Owner and open Home.
2. Confirm the personal greeting (`Hello, [Shop user]`) is the first Home section.
3. Confirm Shop overview follows the greeting and contains its four report cards.
4. Confirm Continue an unfinished sale follows the overview, including the empty state when no unfinished sale exists.
5. Confirm the four primary quick-action cards follow the unfinished-sale section: New sale, Find a product, Customers and Sales, subject to the existing navigation permissions. Their section heading must read **What would you like to do?**
6. Confirm the starting-cash reminder and any READ_ONLY plan shortcut appear only after the four primary quick actions.

## 17. Evidence and stop point

For every P8-25–P8-37 row in `Phase_8_WP3_Manual_Results.csv`, record:

- PHP, CodeIgniter, Shield, MySQL and browser versions;
- synthetic fixture and Shop;
- actor and exact effective permissions;
- selected Sales date range and Shop timezone;
- expected and actual results;
- sanitized screenshot, API response, Network capture or EXPLAIN evidence reference;
- reviewer and Africa/Lagos observation time;
- Pass, Fail, Blocked or Not run.

Do not mark a case Pass from source inspection, static tests, mock responses or an unexecuted command. Stop after WP3 manual verification and wait for explicit Owner acceptance. Do not merge the WP3 PR, integrate to `main`, deploy or release.
