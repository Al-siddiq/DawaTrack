# Phase 4 WP3 owner manual verification

Status update 2026-11-04: **owner accepted WP3 and authorized WP4**. See `acceptance/2026-11-04-phase-04-wp3-owner-acceptance.md`. Historical per-case rows are preserved because individual results/environment evidence were not supplied. Original instructions follow. Use PASS / FAIL / BLOCKED with observed results, tested SHA, PHP/MySQL versions, Shop hostname and browser/device. Focused engineering checks do not prefill these results.

## Setup

Use a backed-up disposable MySQL 8.4 development checkout with accepted WP1/WP2. Keep two active Tenants/Shops A/B on verified hostnames, their Owners and independently granted Staff accounts. Finalize Opening Cash in A; retain B without a baseline for no-Cash tests. Use separate browser profiles for concurrent sessions. Prepare Products with established qty/cost through accountable Intake: P1 qty 10, average cost 60, selling price 100; P2 another price/cost; P3 unestablished zero stock; one inactive Product. Create active and inactive Customers. Capture before counts/projections for each scenario so comparisons are scoped to the tested Sale UUID.

```bash
git fetch origin
git switch codex/wp3-windows-build-fix-20261103
composer install
npm ci
npm run build
php spark migrate:status
php spark migrate --namespace App
php tests/contracts/wp1.php
php tests/contracts/wp2.php
php tests/contracts/wp3.php
php tests/contracts/wp3-posting.php
```

The current follow-up branch includes the Windows asset-build fix and targeted dependency patches. `npm run build` should end with `Frontend assets built.`; Sass deprecation warnings alone do not indicate failure. No Git Bash or separate `sh` installation is required. See `Phase-4/Phase_4_WP3_Windows_Build_Followup_20261103.md`.

WP3 adds no migration. On a current checkout App migrate should have nothing pending. Do not execute raw SQL too. Contract scripts are optional engineering support. The posting script uses in-memory doubles; it does not replace native database checks.

## Payment matrix

Create a separate 20,000 draft for each case. Credit cases have active Customer Alice; test both optional blank due date and a valid calendar date. Review, confirm, reload receipt and inspect scoped DB evidence.

| ID | Cash | Transfer | Expected positive receipt rows | Credit / Receivable | Result |
|---|---:|---:|---|---|---|
| M1 | 20,000 | 0 | CASH 20,000 | 0 / none; Customer may be absent | UNEXECUTED |
| M2 | 0 | 20,000 | TRANSFER 20,000 | 0 / none; no baseline needed | UNEXECUTED |
| M3 | 5,000 | 15,000 | CASH 5,000 + TRANSFER 15,000 | 0 / none | UNEXECUTED |
| M4 | 0 | 0 | none | 20,000 / exactly one; no baseline needed | UNEXECUTED |
| M5 | 5,000 | 0 | CASH 5,000 | 15,000 / exactly one | UNEXECUTED |
| M6 | 0 | 5,000 | TRANSFER 5,000 | 15,000 / exactly one; no baseline needed | UNEXECUTED |
| M7 | 5,000 | 5,000 | CASH 5,000 + TRANSFER 5,000 | 10,000 / exactly one | UNEXECUTED |

All seven cases: paid snapshot + credit snapshot = total; positive actual CASH/TRANSFER rows only, no CREDIT/zero row. Each line has one SALE/SALE_ITEM movement with scoped line FK; header, items, positions, payments, optional credit, Audit and changes commit together.

## Validation, costs and receipts

| ID | Actions | Expected result | Result |
|---|---|---|---|
| V1 | Add qty 3 at price 100/cost 60; post; inspect receipt and DB | Total 300, COGS 180, gross profit before expenses 120; qty decreases 3, average unchanged, draft zeros replaced | UNEXECUTED |
| V2 | Change current Product selling price/cost through real later activity; reopen V1 receipt | Historical amounts/basis/COGS/profit unchanged; Product labels may reflect current master name/unit | UNEXECUTED |
| V3 | Repeat Product lines qty 2 then 4 with initial qty 5/cost 60 | Stock 5→3→−1; two movements/versions, cost 60 retained, COGS 360 on total 600; below-zero warning | UNEXECUTED |
| V4 | Sell P3 from zero/unestablished; later replenish through Intake; reread first receipt | Sale allowed; original zero-basis profit remains provisional/unestablished, not verified; state reconstructed before original movement | UNEXECUTED |
| V5 | Fractional qty .05 (enter 0.05) at price 0.10; multiple rounded lines; zero-price authorized line; near/over money and quantity precision | Half-up line 0.01, header sum of rounded lines; permitted zero-price Sale creates no fake payment; overflow rejected with draft/effects unchanged | UNEXECUTED |
| V6 | Credit without Customer; assigned inactive Customer even fully paid; inactive Product after preview; negative/overprecision/scientific values; JSON float/null amounts; overpay; forged totals/status/cost/scope | 422/403/409/scoped 404, no committed effects; basket retained; active references required anew at posting | UNEXECUTED |
| V7 | Optional due date, invalid leap date, due date with zero credit; device-local occurrence near midnight | Valid date stored exactly as Shop calendar DATE; invalid/full-paid due date rejected; occurrence UTC/offset correct; history filters explicitly use UTC posting dates | UNEXECUTED |
| V8 | Staff with sale.view only; add sale.record; separately test sale.price_override and sale.profit.view; inspect actual JSON | Independent read/post/price/margin authority. No historical cost/COGS/profit fields without margin grant; no Customer phone/notes in Sale data | UNEXECUTED |
| V9 | Product price changes between draft/review/post for unprivileged Staff; then save current price and review again | Old unauthorized quote rejected; current-price save succeeds; Owner/explicit override only for different price | UNEXECUTED |
| V10 | Open receipt/history, paginate/search by Customer/reference, UTC date range; refresh direct #sale/{uuid}; view credit and stock links; print | Correct immutable receipt/actor/time/Customer/money/original credit; scoped stock proof; no edit/delete of posted contents; useful print output | UNEXECUTED |

## Cash, concurrency, rollback and recovery

| ID | Actions | Expected result | Result |
|---|---|---|---|
| S1 | In Shop B without baseline try cash > 0; then transfer-only/full credit; in A create controlled active overlapping CASH/FULL fixture on disposable clone and post Cash | Cash rejected absent baseline/active scope; no-Cash Sales allowed without baseline; no partial stock/payment/credit effects | UNEXECUTED |
| S2 | Two independent sessions submit same UUID/version simultaneously; double-tap UI; repeat valid post after success, including while later Cash control is active | Exactly one set of evidence; one Sale Audit/change aggregate; repeat returns existing receipt, no new money/stock/credit | UNEXECUTED |
| S3 | Two sessions open draft; edit/remove/discard versus post; hold Sale header FOR UPDATE in operator DB session to inspect waiting | Shared parent protocol serializes; stale version/post state rejected; no posted line/header changes through draft routes | UNEXECUTED |
| S4 | Post two Sales sharing Products in opposite basket order; interleave with Intake/manual movement; inspect native locks and diagnostic results | Product/position locks acquired ascending ID, no lost quantities/pointers/version or cost changes; an aborted deadlock leaves no partial effects and permits deliberate whole-command retry after retrieval | UNEXECUTED |
| S5 | Drop successful post response in dev network proxy or browser interception; then Check Sale status; repeat with failure before commit and refresh | Stable UUID; uncertain edits disabled; retrieve POSTED receipt or still-DRAFT state, no blind repost/new identity; transient payment values reset on reload | UNEXECUTED |
| S6 | Separately deny runtime INSERT/UPDATE on stock_movements, stock_positions, sale_items, sale_payments, receivables, sales, audit_events and sync_changes in disposable clone; post suitable mixed credit Sale; restore rights after each | Every injected failure leaves header DRAFT and all prior item/projection/evidence unchanged; no partial Audit/change success; native commit-failure behavior documented if inducible, otherwise BLOCKED | UNEXECUTED |
| S7 | Foreign Tenant/Shop Sale/Customer/Product/item/Receivable IDs; direct APIs; deactivate member, revoke grant, READ_ONLY/BLOCKED/CLOSED, expired login and missing/altered CSRF | Server-enforced scope/authority; safe denial, no foreign contacts/costs/effects. READ_ONLY receipt/history reads remain permitted | UNEXECUTED |
| S8 | Use old manual movement reverse endpoint on Sale-linked movement; attempt draft update/delete after posting; try Return/reverse action as if already implemented | Commercial stock cannot be independently reversed; original immutable. WP4 Returns/correction not represented as delivered | UNEXECUTED |
| S9 | Run inventory:diagnose for explicit Shop scope after accepted Sales; inspect movements, coverage/sign/source, position chain, pointers and versions | Valid Sale evidence is recognized; no Phase 3 anomaly merely because type=SALE; faults reported read-only, no automatic repairs | UNEXECUTED |
| S10 | Phone 360px, tablet/desktop, 200% zoom, keyboard-only and assistive status inspection; empty/history/error/offline/session-expiry; unsaved basket edits before review | Readable reflow, labels/focus, collapsible basket, large quantity, sticky total/confirm; unsaved edits block review; offline write unavailable and no queued success | UNEXECUTED |
| S11 | Regression: Customers/private masking, Opening Cash receipt, Product/stock, Intakes/latest-chain reversal eligibility, manual corrections, Staff permissions and Audit | Accepted workflows preserved; later Sale activity correctly blocks unsafe old Intake reversal | UNEXECUTED |

For S1, active later Cash/Full sessions are not an exposed WP3 workflow. Use a controlled, schema-valid fixture on a disposable clone to exercise the guard; do not invent a finalized session or modify accepted production observations. For S4/S6 use a separate operator connection and inspect actual committed state after restoring rights/closing transactions. The doubles-based contract check is not proof of native rollback/concurrency. The CLI's accepted arguments are shown by `php spark help inventory:diagnose`.

## Cleanup and acceptance

Preserve real posted evidence; WP3 has no reversal workflow. Rebuild a positively identified disposable clone for repeatable cleanup rather than deleting linked posted rows or disabling FKs. Drafts may be discarded with reason; Customers may be deactivated. Never down-migrate the commercial schema around posted references.

Record: tested SHA ___; environment ___; results for M/V/S cases ___; unresolved defects/blocked cases ___; owner decision ACCEPT / REJECT / PENDING ___; owner/date ___. WP3 remains unaccepted until the owner reports that decision. WP4 starts from accepted forward Sale evidence and must resolve the recorded compensation interpretation before dependent Return/reversal code.
