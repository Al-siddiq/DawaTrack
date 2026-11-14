# Phase 6 WP4 — Owner manual verification (P6-31–P6-40)

**Current status — 14 November 2026:** WP4 is [Owner-accepted](acceptance/2026-11-14-phase-06-wp4-owner-acceptance.md); individual outcomes were not supplied. The guide below remains the native verification procedure. See [final release handover](operations/phase-06-final-release.md) for current integration and release gates.
**WP4 is implemented for manual acceptance, not yet accepted, merged or deployed.** Use a backed-up isolated XAMPP development database with synthetic Shops and users. Never seed/delete/edit posted financial rows by direct SQL. Create business evidence through the accepted UI/API workflows.

## 1. Candidate setup

```bat
git fetch origin
git switch codex/phase-6-wp4-expected-cash-20261114
git pull --ff-only
composer install
npm ci
npm run build
npm run ui:release -- --output=docs/Phase-6/Phase_6_WP4_Artifact_Manifest.json
npm run ui:release:check -- --output=docs/Phase-6/Phase_6_WP4_Artifact_Manifest.json
php spark migrate:status
```

Generate the inventory only after the build. Review it and commit the generated manifest to the WP4 branch. WP4 adds no migration. Do not roll back or edit the existing Phase 4 reconciliation tables or Phase 6 financial_movements table.

Merge order after package acceptance: PR #46 (WP2) → PR #47 (WP3) → PR #48 (WP4). The PRs are cumulative; do not merge duplicate package snapshots twice.

## 2. Internal query invocation

There is deliberately no public expected-Cash route. For local verification, use a temporary CLI-only harness in the backed-up development checkout and remove it afterwards. Resolve a real test member/Shop context server-side; do not accept Tenant/Shop IDs from a browser request.

Example invocation inside that temporary local harness:

```php
$db = db_connect();
$context = \App\Modules\TenantShop\Infrastructure\ShopContext::resolve(
    $db,
    $testOwnerUserId,
    'your-verified-test-shop-host',
);
$result = (new \App\Modules\Reconciliation\Infrastructure\ExpectedCashQuery($db))
    ->calculate($context, '2026-11-14 08:00:00.000000', '2026-11-14 18:00:00.000000', '20000.00');
print_r($result);
```

Use the actual test Shop timezone-to-UTC conversion for the boundaries. The baseline must come from finalized Opening Cash or a prior finalized Cash actual, selected by the server. Do not expose this harness through Routes.php or commit it as a public API. If a safe local invocation is unavailable, mark the affected cases Blocked rather than adding a route.

## 3. Deterministic fixtures

Use the approved F1–F6 fixtures in `Phase_6_Manual_Acceptance_Plan.md`. All source events must be created through accepted workflows.

### F1 — mixed Cash sources

1. Record a Transfer Owner Introduction of ₦1,000 before the Cash baseline. Transfer does not change physical Cash and does not require Opening Cash.
2. Finalize Opening Cash at ₦20,000.
3. Correct that Transfer introduction into a CASH FINANCIAL_CORRECTION replacement of +₦500. The exact inverse remains Transfer and is excluded from physical Cash; the replacement contributes +₦500 to the correction net.
4. In the period, record Cash Sale receipts ₦12,000, Cash Sale refund ₦2,000, Cash Debt receipt ₦3,000, Cash Expense ₦1,500, Owner Cash Withdrawal ₦2,000 and Owner Cash Introduction ₦5,000.
5. Query the period and compare every component and source count with the hand-calculation:

```text
20,000 + 12,000 - 2,000 + 3,000 + 5,000 - 1,500 - 2,000 + 500 = 35,000
```

Expected: exactly ₦35,000.00. Transfer source/inverse must not enter physical Cash. The correction replacement must appear once.

## 4. Cases

### P6-31 — F1 hand-calculation

Verify every F1 source, amount, channel, posted_at, component total and source count. Expected Cash is exactly ₦35,000.00. Compare the query's decimal strings with a separately prepared hand-calculation sheet.

### P6-32 — F2 Transfer exclusion

In a fresh Shop with no Opening Cash, record Transfer Expense ₦4,000 and Owner Transfer Introduction ₦6,000. Both are valid for authorized users and neither changes physical Cash or requires Opening Cash. In a local query harness, use a server-supplied synthetic zero baseline only for this exclusion comparison; expected Cash must remain ₦0.00. Do not interpret this synthetic comparison as a finalized Cash period or user-visible count.

### P6-33 — F3 signed correction and no double counting

Within one period, record Cash Expense ₦1,000, then reverse and replace it with a linked CASH FINANCIAL_CORRECTION of -₦800. Verify:
- source SHOP_EXPENSE: -₦1,000;
- exact REVERSAL: +₦1,000;
- FINANCIAL_CORRECTION replacement: -₦800;
- `cash_expenses` = ₦1,000;
- `cash_corrections_net` = +₦200;
- net Financial Movement Cash effect = -₦800.

The original must not be excluded and the inverse must not be subtracted twice.

### P6-34 — posted_at boundary and late posting

Choose a UTC t0. Query with cutoff before the new row's posted_at and then with cutoff equal to/after posted_at. Test a row exactly at t0 (excluded), a row exactly at cutoff (included), and a backdated occurred_at with a posted_at after t0. It is included only in a period whose `(period_started_at, cutoff_at]` contains posted_at. A later posting never changes a previously finalized cutoff.

### P6-35 — integrated Sales, Debt and Financial Movement sources

Run a single synthetic journey:
1. Cash Sale receipt and Transfer Sale receipt.
2. Cash Sale Return/refund and Transfer refund.
3. Cash Debt receipt and Transfer Debt receipt.
4. Cash Expense and Transfer Expense.
5. Owner Cash/Transfer Withdrawal and Introduction.
6. A protected correction and a standalone reversal.
7. Query the expected Cash components.

Every Cash source contributes once. Transfer and Credit do not affect physical Cash. Sale compensation rows follow their explicit opposite RECEIPT/REFUND direction. Debt inverse rows contribute a negative signed correction. Financial Movement inverses/replacements contribute their exact signed values.

### P6-36 — reversal attribution

Reverse Cash Shop Expense, Owner Withdrawal and Owner Introduction records; also correct a record and then reverse its current replacement. Verify each REVERSAL is attributed to its immediate source movement type in `financial_movement_reversals_by_original_type`. Verify all signed inverses/replacements enter `cash_corrections_net` exactly once and the total signed Cash effect matches the raw posted rows.

### P6-37 — no automatic variance balancing

Inspect routes, services and query code. There must be no endpoint or behavior that creates a Financial Movement from an expected/actual Cash variance, overwrites expected Cash, mutates a finalized snapshot or auto-balances a shortage/surplus. A genuine new event must be recorded through its owning workflow. The expected-Cash query must remain read-only and emit no Audit/change evidence.

### P6-38 — history filters, pagination, detail and privacy

Test all five Financial Movement types in the history filter: SHOP_EXPENSE, OWNER_WITHDRAWAL, OWNER_INTRODUCTION, FINANCIAL_CORRECTION and REVERSAL. Combine each with CASH/TRANSFER and Shop-local Date recorded from/to filters. Verify stable `posted_at DESC, id DESC` order, page size at most 50, exact totals, same-public-UUID detail and linked correction history from original/inverse/replacement. Confirm occurred_at is displayed separately and internal IDs/raw Audit metadata/foreign Tenant or Shop records are never exposed.

### P6-39 — responsive, access and online-only states

Complete Owner and Staff journeys on phone, tablet and desktop, including keyboard-only focus and navigation. Verify loading, empty, validation, permission denied, READ_ONLY, blocked, offline, timeout/unknown-save and stale-review states. Financial writes remain online-only; no request is queued, replayed automatically or duplicated after reconnection. Expected Cash is an internal read-only query, not a public UI endpoint.

### P6-40 — final integration and Phase 6 evidence

Review:
- WP1 acceptance and result register;
- WP2 acceptance and result register;
- WP3 acceptance and result register;
- WP4 P6-31–P6-40 observed results;
- final requirements/source/manual matrix;
- defect disposition;
- migration/SQL parity and exact artifact inventory;
- API/module/operations contract;
- release decision template and rollback handover;
- exact base/head/accepted source commits and merge sequence.

Mark each case Pass only after observing it. Native PHP/MySQL, rollback, two-session concurrency, HTTP/session/CSRF, browser/device/keyboard and build evidence must not be inferred from source review. The Owner must make an explicit whole-Phase 6 acceptance decision against a concrete commit. Deployment remains separately authorized.

## 5. Results and escalation

Record case, exact commit, PHP/MySQL/browser versions, Shop, actor/grants, fixture public UUIDs, expected/observed values, evidence reference, reviewer and Shop-local date. A failed or blocked critical case prevents WP4 acceptance. On inconsistent evidence, stop and preserve records; never repair by SQL or create a balancing movement.
