# Phase 4 WP2 — Customers and usable Sale drafts

Date: 2026-11-03. Status: implementation delivered for owner manual verification; **WP2 acceptance pending**. WP1 owner acceptance is recorded in `docs/acceptance/2026-11-03-phase-04-wp1-owner-acceptance.md`.

## Baseline and scope

Approved scope: Phase 4 plan §6; P4-06, P4-07, P4-08 and the draft portion of P4-09, plus preparation for P4-12/P4-13. Actual Sale posting, financial/credit evidence and stock effects belong to WP3; Returns/protected correction and integrated closure belong to WP4.

Baseline: accepted WP1 candidate `3b38cd85f9544b7aa7949791562d6eaafb9ec351`. Delivery branch: `codex/phase-4-wp2-customers-drafts-20261103`. The branch's delivery commit/PR is the repository provenance for this report. WP2 is stacked on the WP1 branch while PR #17 remains unmerged; no merge to main is performed by this delivery.

## Implemented behavior

Customers are Tenant-owned identities with a name, optional normalized/shared phone and minimal optional notes. Search, paginated list/detail, create/edit and reasoned deactivation are available. No stored/editable balance exists. Inactive Customer references remain readable; inactive Customers cannot be newly selected or edited. Phone and notes are excluded from query selections and responses without `customer.private`; phone search is similarly restricted. Quick-create in the basket creates an identity, then explicitly saves its assignment to the draft.

Sale drafts are current-Shop, normal SALE/DRAFT aggregates with permanent UUIDs. They support multiple/repeated Product lines, four-place fractional quantities, line order, exact selling-price estimates, Customer assignment/clearing, business occurrence time, line edits/removal, refresh recovery and reasoned discard. Only active Products available in the current Shop can be added. Products and Customer identities are validated server-side. Draft detail excludes internal IDs, cost placeholders, COGS and Customer contact/notes.

The UI offers bounded searchable Product/Customer pickers, cashier quick-create, saved basket estimates and two-amount CASH/TRANSFER plus credit/due-date preview. Cash/transfer/due-date preparation is **transient**, resets on reload and creates no payment/Receivable. Only basket, Customer and occurrence context persist. The preview uses saved state/version, rejects empty or inactive-Product baskets, overpayment, credit without an active Customer, invalid amounts/dates and due dates on fully paid baskets. A 20,000 total with cash 5,000 and transfer 5,000 estimates credit 10,000. There is no fake CREDIT payment channel, wallet, interest, debt collection or Sale post button/endpoint.

UI writes have pending protection, online/read-only gating and retained error inputs. Saved draft UUIDs are restored through `#sale-draft/{uuid}` on page refresh. Errors direct uncertain outcomes/stale edits to authoritative reload; no background queue, blind retry or durable generic replay is promised. After reload payment entries and unsaved edits reset, as labeled on screen. Device-local business time converts to UTC; due date remains a Shop-calendar date without timezone conversion.

## Schema and apply/rollback

Seven ordered App migrations, paired statement-for-statement with SQL under `docs/database/sql/phase-04/`:

| Suffix after 2026-11-03 | Class | SQL filename |
|---|---|---|
| 100000 | CreatePhaseFourCustomers | 20261103100000_customers.sql |
| 100100 | CreatePhaseFourSales | 20261103100100_sales.sql |
| 100200 | CreatePhaseFourSaleItems | 20261103100200_sale_items.sql |
| 100300 | CreatePhaseFourSalePayments | 20261103100300_sale_payments.sql |
| 100400 | CreatePhaseFourReceivables | 20261103100400_receivables.sql |
| 100500 | CreatePhaseFourReceivableAdjustments | 20261103100500_receivable_adjustments.sql |
| 100600 | AddPhaseFourStockMovementSaleItemForeignKey | 20261103100600_stock_movements_sale_item_fk.sql |

Complete commercial definitions include future posting, original/reversal/replacement relationships, exact amounts, posting provenance, same-scope composite FKs and checks. Payments and Receivables/adjustments have no write workflow yet. Sale items require numeric cost columns; draft-only zero placeholders are hidden and must be overwritten/revalidated atomically with Inventory at WP3 posting. Zero placeholders must never be presented as historical cost or profit.

Use a backed-up development MySQL 8.4 database with the accepted foundation/WP1 migrations. Run `php spark migrate:status`, `php spark migrate --namespace App`, then status again. Starting fresh retains Settings → Shield → App prerequisite ordering. Do not execute raw SQL in addition to migrations. Inspect SHOW CREATE TABLE for all six tables and the stock movement FK; repeating migrate should have nothing pending.

Rollback is destructive and for disposable development only: first remove the late stock-movement FK, then drop adjustments, Receivables, payments, items, sales, Customers. Inspect migration batch membership before invoking the framework rollback command: a batch may contain earlier migrations too. Never disable foreign-key checks to drop referenced history. MySQL DDL implicitly commits; after a partial unrecorded failure inspect/rebuild a positively identified disposable database rather than editing the ledger or blindly rerunning. Before later posted data exists, verify reverse application on a clone. Native apply/rollback has not been executed in this environment.

## Authority, locks, evidence and refresh

See `docs/modules/phase-04-wp2.md` for the API/permission matrix. Known-code Owner authority remains role-derived; Staff grants are independent. `debt.manage`, `sale.reverse` and reconciliation permissions grant none of these new authorities by implication.

Mutation order is Tenant → Shop → active membership → Customer or Sale header → draft children/reference validation → Audit → active sync generation/change publication → commit. Every draft mutation locks its parent with SELECT FOR UPDATE, proves SALE/DRAFT and compares `expected_updated_at`; monotonic timestamps prevent two edits sharing a version. WP3 must acquire the same parent lock before any posting transition. No draft API updates/deletes POSTED evidence. Preview locks the header and verifies the version for a consistent saved-basket estimate without writing evidence. Same-Shop root locking serializes these draft/customer mutations at present; throughput tuning is outside this package.

Customer create/update/deactivate append Audit and publish one Tenant-scoped `customer` UPSERT/DEACTIVATE in the same transaction; Audit or change failure rolls back the Customer write. Metadata lists field names, never raw phone/notes. Draft edits publish no sync, stock or money/debt effects. Explicit saved-draft discard is treated as material and audited as `sale.draft_discarded` with reason/count/estimate. Ordinary draft creates/edits and reads do not add business-evidence events. Publication is last; no domain locks follow generation locking.

Permanent header/item UUIDs and stale-version checks protect ordinary retries. Retrying existing draft creation returns its existing scoped DRAFT. Repeating a line mutation with an old version returns conflict; reload before retrying. Duplicate line order/UUID returns safe conflict. This is not a Phase 10 receipt/replay store.

## Engineering verification and remaining owner verification

Executed: npm dependency installation and frontend build; PHP syntax checking through PHP WebAssembly; 61 focused pure-input/migration-to-SQL source-parity checks; existing 20 WP1 Cash/Audit contract checks; mocked DOM checks covering private-field absence, permanent draft identity, exact-string fractional input, preview versioning, temporary payment reset, stale-edit input retention and offline/read-only controls. `tests/contracts/wp2.php` does not execute SQL; `tests/contracts/wp2-ui.mjs` uses mocked fetch and is not browser layout or HTTP acceptance. No new project dependency/test framework was introduced. Sass emits existing Bootstrap deprecation warnings.

Native PHP/MySQL 8.4 migration execution, exact MySQL arithmetic at runtime, database constraints, concurrency/rollback failure injection, actual authenticated HTTP/CSRF flows and real browser layout/accessibility require development-checkout verification. They are not reported as PASS. Use `docs/manual-verification-phase-04-wp2.md` and record PASS/FAIL/BLOCKED yourself. No owner acceptance is inferred from engineering checks.

No critical defect is known from these checks; runtime verification remains outstanding. WP3 owns authoritative posting/Inventory participant/payments/Receivables/immutable receipts/history. WP4 retains the explicit open compensation interpretation decision from WP1; normal header constraints are not silently relaxed to invent a credit-Return reversal. Phase 4 remains incomplete until all packages and integrated owner acceptance are complete.
