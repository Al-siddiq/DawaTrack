# Phase 4 WP3 delivery — Sale posting, payments and credit

Date: 2026-11-03. Status: implemented for owner manual verification; **WP3 acceptance pending**. WP2 acceptance is recorded in `docs/acceptance/2026-11-03-phase-04-wp2-owner-acceptance.md`.

## Baseline and requirement coverage

Approved Phase 4 plan §7. Baseline: WP2 `19af2527ea48735c4f0fc48b69ef2de7c217e835`, PR #18. Delivery branch `codex/phase-4-wp3-sale-posting-20261103`; the branch/PR commit is the resulting delivery provenance. This delivery is stacked on WP2 while earlier PRs remain unmerged; it does not merge/deploy automatically.

| Plan requirements | Delivered scope |
|---|---|
| P4-05, P4-09 | Cash guard integrated; same parent lock/version contract protects draft/post transition |
| P4-10–P4-13 | All seven forward payment/credit compositions, Customer/due-date validation, exact authoritative totals and overflow checks |
| P4-14–P4-16 | Historical price/cost/COGS and margin; negative stock and cost-state provenance; repeated lines and ordered Inventory locks |
| P4-17–P4-18 | Permanent identity, repeat-safe post/recovery, receipt, history, actor/Customer and stock links |
| P4-25–P4-30, P4-32–P4-33 | Atomic Audit/changes, granular permissions, safe scope/access, responsive cashier/recovery, diagnostic and documentation integration |
| P4-31 | Existing accepted schema reused; no WP3 migration/raw SQL change |

Implementation does not equal native-runtime/manual acceptance. P4-19–P4-24 (Returns, refunds/credit reductions, reversals/replacement) remain WP4. Integrated Phase 4 closure remains pending; no portion is silently deferred to Phase 5.

## Delivered implementation

`SalePostingService` owns the forward command within CommercialAccess's transaction. `SaleStockParticipant` lives in Inventory, requires prelocked Product/position rows and the caller's transaction, and supplies movement/position effects and historical cost snapshots without its own commit. `SaleMoney` supplies exact money addition/subtraction/composition; existing CommercialDecimal supplies MySQL decimal line extensions/COGS. `SaleQuery`/`SaleController` expose safe state/receipt, paginated history and original Sale credit reads.

Every payment variant is supported: Cash; Transfer; Cash+Transfer; full credit; Cash+credit; Transfer+credit; Cash+Transfer+credit. Only positive actual money receipt rows are written; one Receivable is created only for positive credit. Customerless paid Sales are valid. Assigned inactive Customers/Products are rejected at posting; credit requires an active Customer. Due dates are optional local calendar dates. Cash needs finalized Opening Cash and no active overlapping scope; pure Transfer/full-credit does not need a baseline.

Forward Sale posting atomically replaces draft cost placeholders, recomputes amounts, writes one SALE movement per item, updates positions/pointers/versions, writes actual receipts and optional Receivable, transitions the header to POSTED, appends Audit and publishes changes. Failure rolls back all effects. Posted data has no generic edit/delete API; direct manual Inventory reversal cannot independently unwind commercial evidence.

Receipt/history UI includes searchable cashier pickers, large fractional-quantity control, collapsible basket, sticky estimated total/review-confirm checkout, explicit confirmation, pending protection, immutable receipt, print action, actor/Customer, original credit/due date and linked stock proof. Reads work in READ_ONLY; writes are gated online/FULL and by server authority. Unsaved header/line edits block review. Lost/failed posting outcomes preserve the Sale UUID, disable edits and offer authoritative status retrieval; there is no blind repost or offline queue.

Historical margin requires new independent `sale.profit.view` in addition to `sale.view`; Owners derive known-code authority. Receipts hide private Customer contact/notes for all Sale readers. Historical unestablished cost remains labeled provisional even after later stock receipts. See `docs/modules/phase-04-wp3.md` for exact API, permission, lock, error/retry and Audit/change matrix.

## Numeric examples and evidence

- Total 20,000; cash 5,000 + transfer 5,000 → two receipts totaling 10,000 and one Receivable 10,000.
- Full credit 8,000 → no payment row; one Receivable 8,000. Stock and COGS recognized at posting, not collection.
- Qty 3 × price 100 at established cost 60 → sales 300, COGS 180, gross profit before expenses 120.
- Same Product, initial qty 5/cost 60; lines qty 2 then 4 at price 100 → quantities 5→3→−1, total 600, COGS 360, gross profit 240, final RETAINED cost 60. Negative stock is visible, not blocked.
- Unestablished zero basis, Sale qty 2 at 100 → numeric COGS 0 with provisional/unestablished profit warning, never verified profit. Later acquisition cannot change that historical classification.
- Qty 0.05 × price 0.10 → exact half-up line sales 0.01. Header totals sum rounded lines, not a separately rounded unrounded aggregate.

## Apply and rollback guidance

No migration or paired SQL is introduced in WP3. Install dependencies/build assets and confirm accepted WP1/WP2 migrations with `php spark migrate:status`; applying App migrations is a no-op on a fully current schema. Starting from earlier foundation still requires the existing ordered Settings → Shield → App process and seven WP2 commercial migrations. No existing migration or schema policy is rewritten.

Code-only rollback after posting is operationally constrained: preserve all posted evidence, never delete Sales/payments/Receivables/movements to downgrade. Earlier WP2 diagnostics do not understand these new Sale movement types. Use a backed-up disposable clone to compare versions, or forward-fix a defect. Do not run WP2 schema down migrations once referenced commercial evidence exists. Partial/complete reversal is a future WP4 business command, not a database rollback procedure.

## Executed checks and limits

Executed: npm build; PHP syntax checks using PHP WebAssembly and JavaScript syntax checks (26 changed code/assets/test files); `git diff --check`; existing 20 WP1 and 61 WP2 pure contracts; 2,031 exact-money/composition/historical-state checks; 101 posting-orchestration checks using in-memory database/authority/evidence doubles; WP2 and WP3 mocked DOM flows. The posting harness executes the real posting service, CommercialAccess, Cash guard and Inventory participant against doubles; it covers all compositions, repeated lines, negative stock, known-identity repeat, stale/invalid/authority rejection and injected stock/item/payment/Receivable/header/Audit/change/commit failures. It does not execute MySQL SQL or simulate real database concurrency. No new project dependency/test framework is required for owner verification. Optional DOM checks use jsdom installed outside the project. Existing Sass deprecation warnings remain.

Native PHP/MySQL 8.4 migration state/decimal/constraint behavior, real two-session locking/rollback, authenticated HTTP/CSRF and real browser layout/accessibility/printing are **not executed here**. They remain explicit development-checkout manual checks, not PASS. Use `docs/manual-verification-phase-04-wp3.md`; record actual results and acceptance yourself. Owner acceptance is not inferred from the focused checks.

## Remaining phase boundary

WP4 owns original-linked Returns, refunds/Receivable reductions, reversal/correction/replacement, commercial diagnostics and integrated Phase 4 closure. The recorded open credit-Return compensation interpretation requires an explicit decision before dependent WP4 code. WP3 implements ordinary forward Sales and does not resolve that by inventing a second Receivable or changing header rules. Phase 5 collections/statements, Phase 6 non-sale Financial Movements, Phase 7 full Reconciliation and Phase 10 device/offline transport remain outside this package.

## Subsequent Windows setup report and fix — 2026-11-03

The owner reported successful dependency setup, current migration registration and all four PHP contract scripts, but asset build failed because Windows CMD lacked `sh`; Sass warnings were non-fatal. The targeted follow-up replaces shell copying/cleanup with Node APIs and patches the three reproduced npm audit findings without a major/forced upgrade. See `docs/Phase-4/Phase_4_WP3_Windows_Build_Followup_20261103.md` for evidence, limitations, native Windows recheck and current checkout commands. WP3 functional acceptance remains pending.
