# WP4 implementation progress — 2026-11-05

Status: Owner-approved contract; WP4-A schema amendment authored; WP4-B Return API implemented for native verification; WP4-C corrections API implemented for native verification on 2026-11-06; WP4-D implementation candidate delivered on 2026-11-06; integrated native/visual acceptance pending.

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

## WP4-C implemented — 2026-11-06
Protected ordinary Sale/Return reversal, exact inverse money/stock, original-credit cancellation or same-Receivable restoration, linked replacement drafts and explicit read-back/recovery endpoints are implemented. See [C delivery](Phase_4_WP4_C_Delivery_Report.md), [API contract](../modules/phase-04-wp4-c.md), [native checklist](../manual-verification-phase-04-wp4-c.md) and [owner B confirmation/C authorization](../acceptance/2026-11-06-phase-04-wp4-b-owner-confirmation.md). Engineering checks pass; native/MySQL/HTTP/concurrency acceptance remains pending. No C operator screen is claimed.

## WP4-D integrated candidate — 2026-11-06
Operator Return/correction screens, unified receipts/history, Sale usability fixes and compensation-aware diagnostics are implemented. See [D delivery](Phase_4_WP4_D_Delivery_Report.md), [integration contract](../modules/phase-04-wp4-d.md), [final manual matrix](../manual-verification-phase-04-wp4-d.md) and [closure status](Phase_4_Integrated_Closure_Status.md). C confirmation/D authorization is recorded separately. Source/doubles/DOM checks pass; native MySQL/HTTP/concurrency and blocked live-browser checks remain required.

## Remaining acceptance work
Record actual integrated native/browser outcomes, resolve defects, rerun affected checks and obtain explicit owner acceptance. No planned implementation area is silently moved to Phase 5. Phase 4 remains NOT CLOSED until its verification/owner gates are complete; delivery does not merge or deploy the dependency stack.
