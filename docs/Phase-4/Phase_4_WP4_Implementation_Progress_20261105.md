# WP4 implementation progress — 2026-11-05

Status: Owner-approved contract; WP4-A schema amendment authored; WP4 workflows not yet complete.

The owner approved the full Return/compensation proposal on 2026-11-05. Approval is recorded in PR #21. The approved contract is in Phase_4_WP4_Approved_Return_and_Compensation_Contract.md.

## WP4-A authored
- Forward-only migration 2026-11-05-100000 AmendPhaseFourSaleCompensationComposition.
- Preflight blocks unexpected ordinary forward SALE rows whose original_sale_id is non-NULL; no evidence is rewritten.
- New CHECK permits posted reversal-linked compensation headers with root original Sale and NULL received/credit-created snapshots, preserving ordinary forward Sale and Return composition rules.
- Down migration refuses to restore the old CHECK while posted SALE-shaped compensation exists.
- Paired MySQL 8.4 SQL and migration register entry.

## Validation boundary
Static source review only. PHP/MySQL 8.4 execution, migration up/down behavior, existing-data preflight and SQL equivalence have not been run in the user's native checkout. No migration has been applied to any database.

## Remaining WP4 scope
Return draft/preview/post, debt-first allocation/refund caps, Inventory-owned stock/cost provenance, compensation-aware Sale/Return reversal and replacement, permissions, Audit/change events, receipt/history/UI, complete diagnostics, native concurrency/rollback tests and owner integrated manual acceptance remain required. This increment is not WP4 completion or Phase 4 closure.