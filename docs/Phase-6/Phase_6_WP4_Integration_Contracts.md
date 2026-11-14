# Phase 6 WP4 — Expected Cash and final integration contract

**14 November 2026 (Africa/Lagos).** WP1, WP2 and WP3 are Owner-accepted. WP4 is implemented for Owner manual acceptance. This package delivers an internal read-only component query; it does not implement the later full Reconciliation engine.

## 1. Scope and hard boundaries

WP4 composes the expected physical Cash amount from existing immutable sources. It does not create a ledger, snapshot, reconciliation session, count screen, variance adjustment, or public expected-Cash endpoint.

- The only baseline is supplied by the server-side Reconciliation caller from finalized Opening Cash or the previous finalized Cash actual amount.
- ShopContext is server-resolved. Tenant/Shop IDs are never accepted as client input.
- Period boundaries are exact UTC SQL timestamps and use posted_at in the open-left, closed-right interval (period_started_at, cutoff_at].
- occurred_at remains descriptive evidence; it never moves a posted event into an earlier finalized period.
- All source queries run inside one repeatable-read, read-only snapshot unless an owning caller transaction already exists.
- No movement, count, Audit event, change row, balance, variance, or snapshot is written.
- No variance creates a Financial Movement automatically. A genuine new business event must use its owning workflow.
- Transfer and Credit are excluded from physical Cash.
- No migration, raw SQL change, dependency, permission, route, navigation destination, or new bottom tab is added.

## 2. Internal query contract

Class: `App\Modules\Reconciliation\Infrastructure\ExpectedCashQuery`

Method:

```php
calculate(
    ShopContext $context,
    string $periodStartedAt,
    string $cutoffAt,
    string $openingBaselineAmount
): array
```

The Reconciliation caller supplies a server-selected baseline and the authoritative UTC boundaries. Timestamp format is `YYYY-MM-DD HH:MM:SS.ffffff` in UTC. The baseline is an exact non-negative decimal string. The query rejects malformed/reversed boundaries, missing/partial source schemas, inconsistent posted evidence and unsupported decimal ranges.

This class has no HTTP controller or public route. It is called internally by Reconciliation; the caller owns authorization and selects the baseline from finalized Reconciliation evidence.

## 3. Snapshot field mapping and formula

The returned component names map one-to-one to the existing `reconciliation_financial_counts` fields:

```text
expected_amount =
    opening_baseline_amount
  + cash_sale_receipts
  - cash_sale_refunds
  + cash_debt_receipts
  + cash_introductions
  - cash_expenses
  - cash_withdrawals
  + cash_corrections_net
```

Every amount is returned as a canonical decimal string with two fractional digits. Positive-magnitude fields remain non-negative. `cash_corrections_net` and `expected_amount` are signed strings. Source counts and attribution details are additional read-only diagnostics; they are not extra snapshot columns.

## 4. Source-owned Cash semantics

### 4.1 Sales

`Sales\Infrastructure\SaleCashComponentQuery` reads posted `sale_payments` in the current Tenant/Shop and period where channel is CASH.

- direction RECEIPT contributes to `cash_sale_receipts`;
- direction REFUND contributes to `cash_sale_refunds`;
- a Sale/Return compensation payment already carries the opposite direction and is counted in the period when that payment was posted;
- payment/header scope, posted state, correlation, typed Sale Audit evidence and reversal link (same channel/amount, opposite direction, no reversal-of-reversal) are checked;
- Transfer payments and Credit Sales are excluded.

The query counts each posted payment row once. It does not remove an original payment merely because a later reversal exists.

### 4.2 Debt

`CustomerDebt\Infrastructure\DebtCashComponentQuery` reads Cash `debt_payments` and verifies allocation totals, Audit/correlation evidence and any direct reversal link.

- a normal debt payment contributes its positive amount to `cash_debt_receipts`;
- a debt-payment inverse has no direction column, retains the original positive magnitude and links to the source. It therefore contributes the exact negative amount to the signed correction net;
- a corrected replacement is a normal recorded payment and contributes as a debt receipt;
- Transfer debt payments are excluded.

The debt-payment reversal net is exposed separately in attribution and included once in `cash_corrections_net`. This preserves the non-negative `cash_debt_receipts` snapshot field while allowing a reversal posted in a later period to offset the prior-period receipt without rewriting the earlier baseline.

### 4.3 Financial Movements

`FinancialMovement\Infrastructure\FinancialMovementCashComponentQuery` reads only CASH movements and verifies typed Audit evidence and causal links.

- OWNER_INTRODUCTION contributes its positive signed amount to `cash_introductions`;
- SHOP_EXPENSE and OWNER_WITHDRAWAL contribute their negative signed amount as positive magnitudes to `cash_expenses` and `cash_withdrawals`;
- FINANCIAL_CORRECTION replacement rows contribute their signed amount to the correction net;
- REVERSAL rows contribute their exact signed amount to the correction net;
- each reversal is additionally attributed to its immediate original movement type in `financial_movement_reversals_by_original_type`;
- replacement/inverse pairs must match the WP3 source, channel, amount, correlation, recorded time and counterpart evidence. Branches, cycles, missing pairs and invalid links fail closed.

The query sums every in-period immutable row once. It never filters out an original because it has a reversal child and then subtracts that inverse a second time.

### 4.4 Signed correction net

`cash_corrections_net` is the sum of:

1. signed CASH debt-payment reversals;
2. signed CASH FINANCIAL_CORRECTION replacements; and
3. signed CASH Financial Movement REVERSAL rows.

Financial Movement reversal attribution remains visible by original movement type. The signed bucket is necessary because existing reconciliation snapshot magnitude fields (sales receipts/refunds, debt receipts, introductions, expenses and withdrawals) are non-negative, while a reversal may occur in a later period and must be negative or positive in that period.

Sale-payment reversals are already classified by the explicit opposite RECEIPT/REFUND direction and are not added again to this correction net.

## 5. Period, late-posting and no-double-count rules

- All three source queries use the same server-supplied `posted_at > period_started_at AND posted_at <= cutoff_at` predicate.
- The lower boundary is excluded; the cutoff is included.
- occurred_at is returned by existing source detail/history but is not used for period inclusion.
- A record posted after a finalized cutoff is not backdated into that period, even if occurred_at is earlier. It belongs to the period whose posted_at range includes it.
- A reversal is a new posted event. If source and inverse fall in one period, their signed effects net there. If they fall in different periods, the later inverse adjusts the later period only.
- The baseline remains frozen. No query writes a variance-balancing movement or mutates a prior snapshot.

## 6. Consistency, access and error handling

The query uses the existing repeatable-read read-only `DebtReadSnapshot` primitive. Source modules retain ownership of their payment/movement semantics; Reconciliation only composes their component DTOs. All database predicates include the server-resolved Tenant and Shop.

The future Reconciliation service must authorize the caller, resolve the current Shop, choose a finalized server-side baseline and pass UTC boundaries. The internal query does not accept client-supplied IDs or grants and is not an API authorization boundary.

Missing/partial Sales, Debt or Financial Movement schema returns an explicit setup-required error. Invalid source Audit, allocation, reversal, counterpart or decimal evidence fails closed; it is never converted into a zero component.

## 7. Financial Movement history integration

The existing scoped history route remains `GET /api/v1/shop/financial-movements`. It now accepts:

- `page`, `per_page` (maximum 50);
- `channel`: ALL, CASH or TRANSFER;
- `movement_type`: ALL, SHOP_EXPENSE, OWNER_WITHDRAWAL, OWNER_INTRODUCTION, FINANCIAL_CORRECTION or REVERSAL;
- `from_date` and `to_date`: Shop-local calendar dates.

Date filters apply to `posted_at`, use the Shop timezone and preserve the stable `posted_at DESC, id DESC` order. Detail continues to show Date recorded and Date it happened separately. Existing linked correction history navigation is preserved for original, inverse and replacement rows. No internal numeric IDs or raw Audit metadata are exposed.

## 8. UI and workflow boundary

The existing Money movements workspace remains online-only. It retains loading, empty, validation, permission-denied, READ_ONLY, blocked, offline, uncertain-save, stale-review and navigation-protection states. No write is queued or automatically replayed offline. The history type filter is additive; it does not introduce another workspace or navigation destination.

## 9. Source map

| Concern | Owner |
|---|---|
| Sale receipts/refunds and payment reversal semantics | SaleCashComponentQuery |
| Debt receipts and negative debt-payment inverse | DebtCashComponentQuery |
| Signed Cash movement rows, causal validation and reversal attribution | FinancialMovementCashComponentQuery |
| Baseline + components + exact expected amount | ExpectedCashQuery |
| Financial Movement type/date/channel filters | FinancialMovementQuery |
| History type filter and detail navigation | financial-movements-panel.js |

No migration, SQL file, API endpoint, Audit writer, change publisher or snapshot writer is introduced.
