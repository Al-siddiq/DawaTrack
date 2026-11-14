# Phase 6 WP4 — delivery report

**14 November 2026 (Africa/Lagos).** **Implemented for Owner manual acceptance; not yet accepted, merged or deployed.** WP1, WP2 and WP3 are Owner-accepted. WP4 is the final package in the approved four-package Phase 6 sequence.

## Delivered

- Internal read-only `ExpectedCashQuery::calculate(ShopContext, periodStartedAt, cutoffAt, openingBaselineAmount)`.
- Repeatable-read snapshot across source-owned Sales, Debt and Financial Movement component queries.
- Exact decimal-string output mapped to the existing `reconciliation_financial_counts` fields.
- Sales RECEIPT/REFUND direction, compensation/reversal direction and typed Audit/correlation validation.
- Debt receipt allocations, Audit/correlation and reversal-link validation; inverse payments contribute a signed negative correction net.
- CASH-only Financial Movement composition, exact reversal/replacement pair validation, signed correction net and reversal attribution by immediate original movement type.
- Strict UTC `posted_at` interval `(period_started_at, cutoff_at]`; occurred_at does not backdate a finalized period.
- Transfer/Credit exclusion, source counts and attribution details.
- Scoped Financial Movement history `movement_type` filter across all five movement types, preserving Shop-local Date recorded filters and bounded pagination.
- No public expected-Cash route, no snapshot/Audit/change write, no automatic variance balancing, no new migration or dependency.

## F1 hand-calculation

```text
₦20,000 opening baseline
+ 12,000 Cash Sale receipts
-  2,000 Cash Sale refunds
+  3,000 Cash Debt receipts
+  5,000 Owner Cash introductions
-  1,500 Cash expenses
-  2,000 Owner Cash withdrawals
+    500 net Cash correction
= ₦35,000 expected Cash
```

The F1 linked +₦500 correction can be produced by correcting a pre-period Transfer Owner Introduction of +₦1,000 into a CASH FINANCIAL_CORRECTION replacement of +₦500 after the Cash baseline is finalized. The inverse remains Transfer and is excluded; the Cash replacement contributes +₦500. This avoids inventing a Cash source before Opening Cash is ready.

## Verification boundary

The candidate has been reviewed against the approved Phase 6 plan, Blueprint §7.6, Database Schema v1.1 §11.1 and the existing Phase 4 `reconciliation_financial_counts` migration/SQL. The current code-editing environment did not provide a local DawaTrack checkout with native PHP/MySQL/browser execution. Therefore no claim is made that PHP lint, Composer, frontend build, MySQL DECIMAL/query execution, read-only transaction behavior, native HTTP/CSRF, or real browser/device/accessibility checks have passed.

No automated test framework or dependency is added. The Owner must run the build and release inventory commands in the manual guide, invoke the internal query through a temporary local-only CLI harness (never a public route), and complete P6-31–P6-40. The result register remains Not run until observed.

Final handover artifacts:
- Final integration matrix: Phase_6_Final_Integration_Matrix.csv
- Detailed requirement matrix: Phase_6_Requirements_Matrix.csv
- Defect/verification-gap disposition: Phase_6_Defect_Disposition.csv
- Release decision: Phase_6_Release_Decision_Template.md
- Rollback handover: Phase_6_Rollback_Handover.md

## Schema and release

WP4 introduces no migration and does not alter the existing `reconciliation_financial_counts` PHP migration or raw SQL twin. Confirm `php spark migrate:status` is unchanged. Generate and verify the artifact inventory from the built checkout, review it and commit the generated manifest to this branch. The manifest is not fabricated here.

## Integration

WP4 branch: `codex/phase-6-wp4-expected-cash-20261114`. Current source candidate head before the Owner-generated artifact-manifest commit: `6114f910e4f85ea0c25a19e92bfb55891ea9fae1`. PR #48 is a cumulative successor from the accepted WP3 candidate. Merge order remains WP2 PR #46 → WP3 PR #47 → WP4 PR #48. No merge, production deployment, database mutation or whole-Phase closure is performed by this report.

**Exit gate:** Owner completes P6-31–P6-40, hand-calculates F1/F3 and boundary cases, confirms no omission/defect remains, records the exact accepted source commit and artifact inventory, and explicitly accepts the complete Phase 6 programme. Deployment remains separately authorized.
