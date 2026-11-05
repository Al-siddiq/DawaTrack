# WP4 implementation progress — 2026-11-05

Status: Owner-approved contract; WP4-A schema amendment authored; WP4-B Return API implemented for native verification; WP4-C/D and integrated acceptance pending.

The owner approved the full Return/compensation proposal on 2026-11-05. Approval is recorded in PR #21. The approved contract is in Phase_4_WP4_Approved_Return_and_Compensation_Contract.md.

## WP4-A authored
- Forward-only migration 2026-11-05-100000 AmendPhaseFourSaleCompensationComposition.
- Preflight blocks unexpected ordinary forward SALE rows whose original_sale_id is non-NULL; no evidence is rewritten.
- New CHECK permits posted reversal-linked compensation headers with root original Sale and NULL received/credit-created snapshots, preserving ordinary forward Sale and Return composition rules.
- Down migration refuses to restore the old CHECK while posted SALE-shaped compensation exists.
- Paired MySQL 8.4 SQL and migration register entry.

## Validation boundary
Static source review only. PHP/MySQL 8.4 execution, migration up/down behavior, existing-data preflight and SQL equivalence have not been run in the user's native checkout. No migration has been applied to any database.

## WP4-B implemented
Return drafts/read/list/original-line lookup, exact original-line allocation, debt-first preview/post, channel caps and Inventory-owned incoming effects are implemented with required sale.return authority, cost masking, Cash guards, aggregate Audit and atomic refresh. See [delivery](Phase_4_WP4_B_Delivery_Report.md), [module/API](../modules/phase-04-wp4-b.md) and [manual/native guide](../manual-verification-phase-04-wp4-b.md). PHP WebAssembly engineering checks pass; native MySQL/HTTP/concurrency verification remains pending. No browser Return screen is claimed in B.

## Remaining WP4 scope
WP4-C compensation-aware Sale/Return reversal and replacement; WP4-D integrated operator UI/receipts/history, complete diagnostics, native concurrency/rollback/browser verification and owner integrated manual acceptance remain required. B already includes permission/Audit/change essentials needed for atomic Return posting. This increment is not WP4 completion or Phase 4 closure.
