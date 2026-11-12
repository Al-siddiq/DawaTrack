# Phase 5 WP3 — corrections and returns after repayment

12 November2026 (Africa/Lagos). **Delivered for Owner native manual acceptance; not accepted/merged/deployed by this report.**

WP2 acceptance is [recorded separately](../acceptance/2026-11-12-phase-05-wp2-owner-acceptance.md). WP3 branch `codex/phase-5-wp3-debt-corrections-20261112` starts from accepted WP2 `c2b86f9574a591da926cc3bdf6832f44294d3834`. PR #40 was still open and main at `b41425f905100a728a4c6167d5df936702a8502a` when progression was checked. This branch includes accepted prerequisite commits and targets main directly; acceptance does not imply their integration. Capture final candidate SHA from the PR or `git rev-parse HEAD` in the manual register.

## Delivered behavior

- Undo a normal Cash/Transfer payment once with a required reason, preserving original and exact bill allocations. Reject duplicate inverse, inverse chains and unsafe refund dependencies; equivalent UUID replay recovers saved evidence.
- Correct amount/channel/selected bills with **one atomic inverse + replacement**, independent reverse/record/explicit-allocation authority, shared canonical correlation and linked receipts. Failure rolls both back. This follows WP1's binding contract and corrects the older P5-27 two-step expectation explicitly in the manual plan.
- Make factual signed bill increases/decreases, see change history and undo an eligible correction with an exact inverse. No editable balance/write-off/interest/wallet; Sales-owned changes stay Sales-owned.
- Process ordinary eligible Returns after partial/full collection: clear remaining bill first, then refund within attributable Cash/Transfer payments. A payment spanning bills is not counted in full for each Sale. Protect refunded money from collection undo and retain exact Inventory/cost correction guards.
- Extend live Sale-credit reads; preserve historical snapshots. Update allocation-aware balance/refund and adjustment-source diagnostics; add payment sum/scope/Customer/order/inverse checks.
- Add contextual correction/review/confirmation/status/history screens in Customers using existing shell/brand/icons, required reasons, loading buttons and recovery guards. Minimal host/user/Shop-scoped markers contain only IDs; no automatic replay or offline queue. Receipts link original/undo/replacement. Payment correction reason is reverse-authorized and private on Print.
- Reuse shared payment/adjustment participants, no extra schema or library. Audit and server-change evidence remains in the same owning transaction, generation last.

## Verification observed here

| Check | Result / boundary |
|---|---|
| Production build | Passed, `Frontend assets built.`; existing Sass/Bootstrap deprecations;34 bundled icons unchanged (17,008 bytes/5,102 gzip) |
| PHP grammar |249 App files accepted by PHP8.3 grammar parser; **not native PHP execution** |
| Published JS/imports |45 modules parse;135 local import/re-export edges resolve |
| WP1 schema SQL twins |Both migration/raw SQL pairs unchanged and equal; no WP3 DDL |
| New UI host probes |16 actual Vue rendered variants; correction review/modal/save/link, unknown/reload/status,404/same-pair continuation, stale input, refund dependency guidance, independent grants/READ_ONLY/offline, adjustment/detail/history, pending-flow redirection; zero Vue warnings; mocked responses |
| Regression |Prior WP2 12 host variants/payment workflows and Print filtering pass; WP1 10 read-only render variants pass; additional private correction reason Print filtering passes |
| Actual SQL probes |21 assertions against settlement/projection and five changed diagnostic queries on SQLite integer fixtures: F4, full repayment, split bill/channel capacity, inverse/compensation, legitimate increases/decreases, invalid evidence and foreign scope; prior9 projection/four receipt/allocation scenarios pass |
| Exact release set |383 files; `72893c9da9d051e59c9cc5eb8acfe348bbeaf54e13a87d25cfb585a3cfe49754`; source/published parity |
| Clean archive/Windows conversion simulation |374 CRLF text files restore to all383 exact recorded files; repeat check passes; genuine edits reject before repair. No manifest regeneration; not native Windows |
| Documentation/coverage |30 requirement IDs and29 navigation rows retained;788 wording IDs (100 new);13 pending native cases P5-24–P5-36; prior package inventories retained |

These checks do not execute PHP posting, native MySQL DECIMAL/constraints/locks/transaction rollback, authenticated HTTP/session/CSRF/grant races, or real browser/phone/accessibility/Print/deployment. No automated test suite/framework or scratch harness is committed. Native checks remain explicitly pending in the [results register](Phase_5_WP3_Manual_Results.csv); they are not prefilled Pass.

## Review and release handover

[Native setup + exact cases](../manual-verification-phase-05-wp3.md) · [Developer/API/transaction/recovery contract](Phase_5_WP3_Integration_Contracts.md) · [Operations and safe correction order](../operations/phase-05-debt-corrections.md) · [Engineering evidence](Phase_5_WP3_Engineering_Evidence.json) · [Exact inventory](Phase_5_WP3_Artifact_Manifest.json).

No new migration/dependency; install existing locks, build, confirm WP1 migrations and check the WP3 inventory before native review. On Windows, use the documented exact `--fix-line-endings` check only when conversion is reported; never regenerate to hide real differences. Code rollback must remain compatible with accepted collection/return evidence; no destructive database reset or protected-row edits.

**Next gate:** Owner manual review/acceptance of WP3. WP4 Customer statements and whole-phase closure are not implemented or authorized by this delivery.
