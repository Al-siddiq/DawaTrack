# Phase 7 WP4 — final integration and release handover

**17 November 2026 (Africa/Lagos). WP3 accepted. WP4 candidate prepared for Owner review.** [Owner instruction](../acceptance/2026-11-17-phase-07-wp3-owner-acceptance.md). Whole-phase acceptance and release remain pending; no native case Pass is inferred from “Everything now works”.

## Changes

Stock detail/page/save no longer load all captured items into PHP. Progress uses scalar totals, pages fetch only their rows/proofs and finalized links, and a row save validates its own proof. Whole-count confirmation, finishing and adjustment still validate the full immutable evidence. Stock/Full count revision v2 uses count-write Audit identity and entered values only; expected values/digest are excluded. An old page gets the existing stale409 and must reload/check before an explicit retry.

Blind/non-review readers no longer receive free-text cancellation reasons, and Device exclusion reasons wait for authorized non-blind review. Multiple late sources targeting one Full check publish one check refresh per batch while retaining distinct source associations. No schema, grant, dependency, balance rule or private configuration change.

The shared pages stop count/confirm/readiness/review actions after a generation change and explain how to cancel safely. Successful saves reuse their scoped detail response once; explicit saved-status checks still read afresh. Confirmed/recovered cancellation drops unsaved item drafts and keeps already saved evidence. Native dialogs now contain Tab/Shift+Tab and retain Escape/return-focus behavior. All shop wording follows the Owner’s plain-English rule.

## Evidence and limits

| Check | Observation |
|---|---|
| Locked frontend build | Passed; existing Sass deprecation warnings; no new dependency |
| PHP grammar |303 App PHP files parsed with PHP8.3 grammar; native PHP/Composer unavailable |
| Stock evidence | Actual PHP8.3 WebAssembly class and SQL on137-item SQLite fixture: progress/zero, pages/search/captured rename/literal percent-underscore, scoped row save, bounded rows/proofs, full digest/tamper/missing/duplicate rejection and v2 token behavior passed. At most6 helper queries and21 fetched rows per proof query for a20-row page. FOR UPDATE recorded/stripped; not MySQL locks/DECIMAL/plans |
| Privacy DTOs | Actual ControlQuery under PHP8.3 WebAssembly: five blind/non-review/count/review/cancelled combinations passed. Repository/access/Device/snapshot dependencies mocked; not native access acceptance |
| Late publication | Actual ChangeSequenceService under PHP8.3 WebAssembly: shared Full target/existing refresh/disjoint target cases passed with3/3/4 published rows. Source association and DB mocked; no transaction/rollback claim |
| UI recovery regression | Actual Vue component with mocked API/dialog methods passed Stock/Full/Cash explicit zero, unrelated draft retention,49→46 adjustment preview, original-ID lost-reply GET recovery, offline clearing and fresh-known setup503 versus unknown500 handling |
| Browser/keyboard | See [engineering observations](Phase_7_WP4_Engineering_Observations.md). Actual ShopApp/CSS/Vue/native dialogs under headless Chrome with synthetic HTTP fixtures; not native PHP/auth/CSRF/Device/cookies or physical-device acceptance |
| Source/public and inventory | Locked build and exact candidate inventory verified; hashes recorded in engineering observations |
| Traceability |32 original requirement IDs;48 original case IDs and12 WP4 cases preserved, all individual native cases remain Not run; historical manifests/results unchanged |
| Native release gates | Native MySQL migration/FKs/arithmetic, source concurrency/failure/generation, real sessions/CSRF/Device cookies, full security/performance and backup/restore/compatible rollback remain unobserved here |

Scratch tools stayed outside the repository. No test framework or automated suite was added. Bounded returned rows do not establish bounded native scanning or latency. The [high verification blockers](Phase_7_WP4_Defect_Disposition.csv) keep release on HOLD until supported evidence and explicit whole-phase Owner acceptance exist.

## Review and handover

[Final32 requirements](Phase_7_WP4_Final_Requirements_Matrix.csv) · [Source matrix](Phase_7_WP4_Source_Effect_Matrix.csv) · [Final contracts](Phase_7_WP4_Integration_Contracts.md) · [Integrated48 results](Phase_7_WP4_Integrated_Manual_Results.csv) · [WP4 twelve results](Phase_7_WP4_Manual_Results.csv) · [Phase-style native guide](../manual-verification-phase-07-wp4.md) · [Operation/backup/restore/rollback](../operations/phase-07-final-handover.md) · [Trader guide](../operations/phase-07-shop-checks.md) · [Closure checklist](Phase_7_Closure_Checklist.md) · [Release decision](Phase_7_Release_Decision.md) · [Exact inventory](Phase_7_WP4_Artifact_Manifest.json).

Branch `codex/phase-7-wp4-final-handover-20261117` starts at accepted WP3 `c9d8b996a830ce1ce3248a46d7516d7524e1ea80` and includes WP1–WP3. Main observed at preparation: Phase6 closure `42664001a18c9678022d85fd85e7be33b7640e3f`. Review the cumulative PR and actual ancestry before any integration; earlier separately squashed packages need tree reconciliation. Exact delivered head is available on the PR; bind the eventual Owner decision to that immutable commit and inventory, not the branch label.

No main merge, production migration, private key change, business mutation or deployment was performed. Phase8 requires its own plan/authorization. WP4 implementation/handover is ready for review; native verification and formal phase closure are still open.
