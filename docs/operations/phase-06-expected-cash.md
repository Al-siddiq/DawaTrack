# Phase 6 WP4 — expected Cash operations and support

## What expected Cash represents

Expected Cash is a read-only calculation of physical Cash at a server-selected cutoff. It is not a bank balance, a general ledger, a mutable balance or a reason to create an adjustment transaction.

```text
Opening Cash baseline
+ Cash Sale receipts
- Cash Sale refunds
+ Cash Debt receipts
+ Cash Owner introductions
- Cash Shop expenses
- Cash Owner withdrawals
+ signed Cash correction net
= expected physical Cash
```

Transfer and Credit are excluded.

## How corrections appear

- Sale payment reversals use the opposite RECEIPT/REFUND direction and appear as a receipt or refund in the period when posted.
- A Debt payment inverse is a negative signed correction; a normal corrected replacement is a new positive Debt receipt.
- Financial Movement REVERSAL rows use their exact signed delta and retain attribution to the immediate original movement type.
- FINANCIAL_CORRECTION replacement rows use their signed amount.
- The original and inverse are both counted once if they fall in the same period. If the inverse is posted later, only the later inverse affects the later period; the earlier finalized baseline is not rewritten.

The signed `cash_corrections_net` field includes Cash Debt payment inverses and Cash Financial Movement inverses/replacements. This allows the existing snapshot schema's other component fields to remain non-negative magnitudes.

## Period boundaries

The Reconciliation caller supplies exact UTC `period_started_at`, `cutoff_at` and baseline. Events are included only when `posted_at > period_started_at AND posted_at <= cutoff_at`. `occurred_at` is displayed for context but does not move an event into a closed period. A late-posted event belongs to the period in which it was recorded.

## When evidence is inconsistent

If the query reports missing setup or inconsistent Sales, Debt or Financial Movement evidence:
- stop the count/review using that result;
- preserve the source records and request correlation/public UUIDs;
- inspect the owning Sale, Debt payment or Financial Movement history and Audit evidence;
- escalate to technical support.

Never convert missing source evidence into zero, manually change a posted row, delete an Audit event, edit a finalized snapshot or add a Financial Movement to erase a shortage/surplus.

## Scope and online behavior

WP4 adds no public expected-Cash API and no new reconciliation screen. The later Reconciliation engine will authorize the user, select a finalized baseline and call the internal query. Existing Financial Movement history filters include type, channel and Shop-local Date recorded. Financial writes remain online-only; do not queue or replay offline.

## Release and rollback

WP4 adds no migration or dependency. If a query or history defect appears, stop using the expected-Cash result, preserve all immutable source/Audit evidence and roll back the application code to the last deployed commit using the release procedure. Do not delete or rewrite posted Sale, Debt, Financial Movement or Audit rows. Do not roll back Phase 4/6 migrations or create a manual SQL balance. Re-enable the query only after source review and the affected native manual cases pass.
