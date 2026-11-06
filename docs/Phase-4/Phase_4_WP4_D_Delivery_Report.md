# WP4-D delivery — integrated workflow and Phase 4 verification candidate

Date: 2026-11-06. **Implementation delivered for integrated owner verification. WP4 and Phase 4 acceptance/closure remain pending.** Base: WP4-C PR #25 at `024f8276a249c4d7631699c74f18fa2346860900`. Branch: `codex/phase-4-wp4-d-integration-20261106`. No merge, deployment or database migration is performed.

The owner confirmed C and authorized the next planned implementation. That instruction is recorded without inventing native test results. The approved business/schema contract and accepted WP1–WP3 scope are preserved; later collections, generic Financial Movements, full Reconciliation, reporting and offline device transport remain outside Phase 4.

## Delivered integration

| Requirement area | Implementation evidence |
|---|---|
| Return UI | Original posted Sale → Return Products → original-line quantities → save/read-back → debt-first estimate → actual refund/reason → server review → explicit confirmation → linked immutable receipt |
| Correction/replacement UI | Protected full-operation reversal preview/reason/checkbox; dependency errors; linked original/compensated/reversal/replacement records; separate ordinary replacement posting |
| Recovery and authority | Same-identity read-back after uncertain Return/reversal/create/replacement outcomes; pending/write/read-only/offline controls; current backend grants remain authoritative; cost fields masked separately |
| Receipts/history | Unified public Sale/Return/compensation detail; record-type filters, saved Return drafts, pagination/search/dates, actor, original/reversed markers, actual money and existing-debt effects, signed margin and stock proof links |
| Sale usability | Total no longer overlays inputs; numbered Products → basket → payment steps in order; Product choice/auto-price guidance; optional Customer/time and advanced ordering collapsed; cancellation separated; compact confirm action only is sticky |
| Printing | Isolated copy of permission-filtered receipt, opened stock proof and removal of controls; cleanup after print invocation; current application view is preserved |
| Diagnostics | 18 scoped bounded SQL categories plus read-only historical cost-state comparison; compensation totals/line/payment/adjustment/source graphs, missing zero-value inverse lines, refund limits, adjusted-debt bounds and replacement relationships; existing Inventory compensation movement coverage |
| Final verification package | Integrated Windows/manual walkthrough, per-requirement P4-01–P4-33 matrix, native concurrency/rollback/migration/accessibility gates and explicit pending closure record |

A read-back defect was found during integrated DOM testing: a B save response omits original-line choices, and the subsequent richer GET can have the same version. Hydration now follows the authoritative record object, so canonical saved quantities clear the dirty flag and calculation can proceed. Replacement response wrappers are normalized through unified detail; source-specific component keys prevent retained quantities/review from another Return. Print markup contains only the authorized receipt rather than the surrounding workspace.

## Executed evidence and limits

Executed: PHP syntax via WebAssembly; JavaScript syntax and cross-platform frontend build; prior WP1/WP2/WP3/B/C contract regressions; D's 81 integrated read/privacy/diagnostic-source/snapshot assertions with explicit doubles; existing customer/Sale and Return/correction mocked DOM checks; a new integrated Vue/jsdom history → Return save/read-back → settlement → lost response → compensation recovery → replacement/read-back flow and isolated print markup/cleanup; source/generated-asset parity and whitespace checks. Project dependencies are unchanged; optional test tooling was installed outside the repository.

**No native MySQL statements or authenticated HTTP/concurrency checks were executed.** Source/doubles do not establish SQL syntax, constraints, arithmetic, locking, migration or rollback acceptance on the user's database.

**Live browser checks are blocked, not passed.** Local Chromium failed while creating its required socket (`Operation not permitted`); the available browser could not reach the local mocked fixture (`ERR_CONNECTION_REFUSED`). The optional reproducible Chromium/axe harness is provided, but desktop/phone layout, live accessibility, keyboard/zoom and actual printed output remain native/manual gates. jsdom has no layout engine; print markup checks do not certify paper output. No screenshot or browser PASS is invented.

## Closure status

All planned Phase 4 implementation areas now have code/documentation coverage across WP1–WP4 A/B/C/D. This is a **closure candidate**, not accepted closure. Retain prior owner acceptance records. Use [integrated manual verification](../manual-verification-phase-04-wp4-d.md) to record actual outcomes, resolve defects, then obtain explicit owner integrated acceptance before recording Phase 4 complete. No requirement is silently moved to Phase 5.

The ordered schema amendment remains WP4-A's 2026-11-05-100000. B/C/D add no migration. Keep posted evidence and respect the used-compensation downgrade refusal. Merge/deployment remain separate from delivery/acceptance. See [integration contract](../modules/phase-04-wp4-d.md) and [Phase 4 closure status](Phase_4_Integrated_Closure_Status.md).
