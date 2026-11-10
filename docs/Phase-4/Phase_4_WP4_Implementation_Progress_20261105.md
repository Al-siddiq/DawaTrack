# WP4 implementation progress — 2026-11-05

Status updated 2026-11-07: **Phase 4 owner manually tested, accepted and closed; implementation merged into main.** See [integrated acceptance](../acceptance/2026-11-07-phase-04-integrated-owner-acceptance.md). Earlier dated sections below retain delivery-time verification boundaries.

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

## Final integrated acceptance — 2026-11-07
The owner reports reviewing/manual testing everything and explicitly accepting the delivery, then merging its PR stack. GitHub merge status and main content are verified. See [closure status](Phase_4_Integrated_Closure_Status.md) for the accepted main baseline and next-phase boundary. Earlier pending owner-acceptance statements are historical; independent native/visual test results are not invented. Phase 5 planning/implementation requires its separate scope and authorization.
