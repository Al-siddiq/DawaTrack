# Phase 5 WP3 — native manual verification

Candidate branch: `codex/phase-5-wp3-debt-corrections-20261112`, based on accepted WP2 `c2b86f9574a591da926cc3bdf6832f44294d3834`. Record the actual branch commit with `git rev-parse HEAD` before reviewing. Date:12 November2026 (Africa/Lagos). **Native cases below are Pending; this guide is not evidence of execution.**

Use an isolated backed-up XAMPP/development database and synthetic Customers/products only. Do not reset production, delete evidence, bypass Cash/stock guards or restore an earlier DB over later accepted events. Accepted WP1/WP2 prerequisites are included; main was still behind them when this branch started. [Contracts](Phase-5/Phase_5_WP3_Integration_Contracts.md) · [operations/recovery](operations/phase-05-debt-corrections.md) · [results](Phase-5/Phase_5_WP3_Manual_Results.csv).

## Setup and first use

```bash
git fetch origin
git switch codex/phase-5-wp3-debt-corrections-20261112
git status --short
git rev-parse HEAD
composer install
npm ci
npm run build
php spark migrate:status
npm run ui:release:check -- --output=docs/Phase-5/Phase_5_WP3_Artifact_Manifest.json
```

No WP3 migration. If either WP1 migration is missing, follow its migration guide once (`php spark migrate --namespace App` only on the positively identified development DB). Do not use migrate:refresh/rollback. If exact inventory checking identifies Windows CRLF conversion, repeat with `--fix-line-endings`, then the plain check; no manifest regeneration. Use your normal Shop address, close old trading tabs and hard reload. Sign in as Owner.

Customers → Money customers owe → View bills → View customer payments → View receipt → **Undo payment / Correct payment**. For bill corrections: View bill → **Change bill amount**; prior changes: **View bill changes** → View bill change → Undo this bill change. Read the explanation, enter required reason, review, check before/after values and confirm in the modal. A corrected replacement saves both new records together. The receipt links original, undo and replacement. For returned goods use the existing original Sale → Return goods flow, never a generic bill decrease.

Prepare independent fixtures via normal commands (not direct ledger inserts):

- F1: Customer bills A1000, B600, C400 in that posting order, no sale-time money. F2: receive1200, leaving A0/B400/C400; total800.
- F4: new Sale10 units×100, all credit, known stock/cost; collect600 Cash, leaving400.
- F5: new bill1000, collect200, leaving800. Record factual decrease50, leaving750; undo restores800.
- Fresh separate Transfer-only Shop without Starting cash; fresh Cash Shop with legitimate finalized Starting cash (zero allowed). Prepare an active cash-count guard case through existing approved flow.
- Separate Staff profiles: view only; reverse only; reverse+record; reverse+record+allocate; adjust only; old broad debt.manage only; return operator; separate sale.reverse. Use second Tenant/Shop, inactive Staff and READ_ONLY. Device in another timezone, narrow/mobile viewport and long names.

## Required cases

| ID | Steps | Expected, including negative checks |
|---|---|---|
| P5-24 | On fresh F2 open1200 Cash receipt, Undo, enter factual reason, Review, confirm. Repeat with Transfer in no-Cash-baseline Shop. Try Cash with baseline missing/active count. | Original remains; inverse same channel/amount and A1000/B200 allocations; total2000 once. Cash guard applies to Cash undo; Transfer requires no Cash baseline. No Sale revenue/stock mutation. |
| P5-25 | Repeat the same final request body/UUID in browser Network developer tools; then change reason/body with same UUID. Try another inverse of the original and inverse-of-inverse by scoped HTTP. | Equivalent request recovers same receipt, no new effects/Audit/changes. Different body conflicts. One inverse; no chain. New UUID must differ from source. |
| P5-26 | Fresh F2, Correct1200 Cash to900 Transfer, oldest first. Review undo1200/replacement900. Separately test EXPLICIT500 to B and C (B100,C400), including a previously paid bill when undo restores it. | Atomic pair shares canonical correlation, original untouched. Default corrected total1100, A100/B600/C400; effective Cash0/Transfer900. Explicit example total1500, A1000/B500/C0. Receipt links all identities; no duplicate physical money. Both original and replacement Cash guard rules apply. |
| P5-27 | Try replacement without record/allocate grants, excess amount or mismatched selected sum. In isolated fault copy fail after inverse insertion, then after replacement insertion. | Binding WP1 atomic contract supersedes earlier two-step wording: **neither half commits** on any failure. Existing normal payment remains effective. Separate standalone undo + later failed normal collection is honestly two independent commands, never labelled atomic correction. |
| P5-28 | Execute F5, inspect original and live bill; View bill changes and undo decrease. On fresh bill1000 apply factual increase200, then eligible exact inverse. | F51000−50−200=750; inverse restores800. Increase allows1200 above original ceiling, inverse1000. Required reason/source identity/before-after visible. No Cash/stock/revenue invented. |
| P5-29 | Attempt decrease801 against F5's800; zero/negative wrong sign/more than2 decimals; empty/long reason; unknown waiver/interest/set-balance type; foreign bill/source UUID. Try generic undo of Sales-owned Return reduction. | Rejected without effects. No generic Sales-owned inverse, cross-scope information or mutable balance. Cancelled Sale cannot be given a new obligation through adjustment. A later payment that consumed an increase can block its decreasing inverse. |
| P5-30 | F4: Return5 units worth500; review debt/refund, choose100 Cash refund, confirm, inspect original/Return/Customer/stock history. | Debt400 reduced400, refund100, debt0; stock+5 at existing historic cost; Cash capacity500. No broad COLLECTION_DEPENDENCY block; historical Sale snapshots unchanged. |
| P5-31 | Fully repay fresh1000 bill, Return5×100; then payment700 split300 to SaleA and400 to SaleB, settle remaining debts separately as needed and test refund choices by channel. | Fully paid Return500 refunds500/debt reduction0. A's attributable addition300, B400; never700 each. Wrong channel/excess refund blocked. Adjustments contribute no cash. Current balance never below0. |
| P5-32 | After F4 Return attempt undo/correct original600 collection. Open linked original Sale and eligible Return; undo Return with existing sale.reverse, then review undo600 again. | First blocked because capacity500<600; safe order and Sale links available. Return compensation restores debt400/refund capacity600; eligible payment undo restores debt1000 and capacity0 once. If later stock chain prevents exact Return undo, it stays blocked; do not bypass. |
| P5-33 | Reverse eligible Return after collection; repeat with legitimate factual increase above original credit. Try exact original Sale cancellation while active collection exists (also offset with adjustment in isolated fixture). | Return's precise debt/refund/stock restored, no false original-credit ceiling. Original Sale cancellation requires collections/changes/Returns safely undone and existing stock chain valid; an offsetting adjustment cannot conceal collection dependency. |
| P5-34 | Two browser profiles prepare conflicting collection/reversal/adjustment/Return against same bill, confirm almost together. Race two different inverse UUIDs of one original. Revoke grant/membership or move to READ_ONLY between review/final. | Current authority and locks serialize. Stale review rejected; one inverse at most; no extra allocation/excess refund/negative debt. Any deadlock/timeout is a failed or uncertain command, never a partial success. Recover by same UUID. |
| P5-35 | Use the isolated controlled-failure and lost-reply procedures below, including failure after each half, Audit and publication. Reload unknown screen and check status; test404 then manual same-ID continuation. | Whole transaction rollback on injected failure. Committed lost reply recovered by GET inverse/adjustment UUID; correction links replacement, no new POST. Marker stores IDs only; no auto queue/replay. Changed-body collision stays unresolved for status checking. |
| P5-36 | Test each independent grant, old broad grant, READ_ONLY/BLOCKED/inactive/foreign Shop, expired session and CSRF rejection. Run diagnostics before/after valid corrections and refunds. Repeat uncollected credit Return and exact stock reversal guards. Check keyboard/mobile/offline/long labels/modal/loading/validation/Print. | No permission shortcut or private/cost leak. Legitimate increases and attributable collections not flagged as corruption. Existing Phase4 behavior preserved. Labelled icons, visible focus, modal escape/focus return, one save while loading; errors remain correctable. Payment undo is labelled as undo, not money received. Printed payment copy omits live/private reason, including browser Ctrl+P. |

Each case may have multiple subchecks. Record observed IDs, amounts, roles/grants, environment/device and actual outcome in the CSV; do not fill Pass from this table or a successful build. More than one results row per case is allowed. Owner package acceptance is separate from missing per-case detail.

## Safe fault / uncertain-response mechanisms (development only)

Use a disposable separate checkout with Xdebug or a temporary **uncommitted** `throw new \RuntimeException('WP3 manual fault');` inside the owning callback. In `DebtCorrectionService::postPayment`, stop after inverse participant append, then after replacement append; in `postAdjustment`, after participant append; and in `execute`, after Audit has run, then just after `$this->changes->publish($commands)` but before returning from the callback. Exceptions there must reach `CommercialAccess::mutate` and rollback payment/allocation/adjustment/Audit/change rows together. For commercial Return failures use existing ReturnService owning callback after stock, refund, adjustment, Audit and publication. Record before/after counts and IDs using read-only SQL in the isolated scope. Never commit the fault change or globally disable Audit. Restore exact files, rebuild and recheck package inventory before normal verification.

To prove **committed lost reply**, break in `DebtCorrectionController::command` after `service()->execute(...)` returns (the transaction has committed), before receipt/status query/HTTP response. Let browser request timeout, resume server, then Check saved status or reload and recover with the original inverse/adjustment UUID. For a correction confirm linked replacement exists with same accepted correlation; do not resend with a new ID. A break inside the transaction or an offline request before send does not prove committed loss. Restore debugger/fault setup afterwards. If the required tool is unavailable, record Blocked rather than claiming rollback/lost-reply verified.

For read-only diagnostics use:

```bash
php spark sales:diagnose TENANT_PUBLIC_UUID SHOP_PUBLIC_UUID
```

Use actual Tenant and Shop public UUIDs, not numeric database IDs. It samples up to100 anomalies/check; it repairs nothing. Keep uncollected Return/stock-cost regression evidence and print previews with the result register. No whole-phase acceptance or deployment is implied by this package.
