# Phase 6 WP2 — delivery report

13 November 2026 (Africa/Lagos). **Implemented for Owner manual acceptance; not accepted, merged or deployed.** [WP1 acceptance](../acceptance/2026-11-13-phase-06-wp1-owner-acceptance.md) is recorded from the Owner's explicit instruction. WP1 individual native observations were not separately supplied and are not fabricated.

## Delivered outcome

The existing Money movements workspace now offers the Owner **Owner took money** and **Add money to shop**. Both have Cash/Transfer selection, required explanation, optional reference/date, authoritative preview, modal confirmation, loading/error guidance and saved history/detail. Cash withdrawal reduces physical cash; Cash added increases it. Transfer has zero physical Cash effect and needs no Starting cash. Cash added is a genuine later movement, never a substitute for the finalized Starting cash baseline or a sale/debt receipt.

OwnerMovementService provides explicit operation methods; a shared MovementRecordingService retains atomic insert → required FINANCIAL Audit → change publication last. Current active PRIMARY_OWNER membership is reselected under the owning Tenant/Shop/membership lock boundary at preview and save, even on retry. Staff with every financial permission are denied. ExpenseService remains a stable facade with its original expense-v1 fingerprint, preserving accepted expense replay. Body type/sign/actor/scope cannot be selected by the client. Owner movement fingerprints include action; cross-action UUID reuse conflicts.

Interrupted-save recovery retains the public UUID and a safe action tag, routes back to the original action, checks scoped GET status and never queues/reposts automatically. Old UUID-only expense markers still recover. A changed route/role invalidates an open confirmation. History/detail show specific movement type, amount/channel and cash direction; Activity uses Money record saved. Prior sidebar/bottom navigation structure is retained.

No new migration, package dependency, broad grant, mutable balance, Owner identity impersonation, arbitrary type endpoint, undo/correction writer or expected-Cash engine is added. Accepted schema/SQL and previous manifests remain unchanged. WP3/WP4 remain planned.

## Verification performed

| Check | Observed evidence | Limit |
|---|---|---|
| Production asset build | Passed; Frontend assets built | Existing Bootstrap/Sass deprecation notices remain |
| PHP grammar | 264 app PHP files parsed as PHP 8.3 grammar | Not native PHP execution |
| Published JavaScript | 48 modules / 149 local import edges checked | Not native browser execution |
| Schema/read/source | Accepted exact migration/SQL parity; eight scoped repository/history SQL-shape probes on SQLite text-money fixtures; explicit action/sign dispatch, locked Owner placement and expense hash compatibility reviewed | No native MySQL DECIMAL, posting, constraints or lock execution |
| Actual Vue custom host + mocked API | 38 expense/Owner render and workflow variants; zero Vue warnings; four Owner action/channel combinations, lost reply, same-ID/action continuation, Staff direct hashes, role change, history actions and route validation | Not native HTTP/PHP/MySQL/browser acceptance |
| Existing Debt UI regressions | Payment host checks passed; 16 correction scenarios/15 assertions; 16 statement variants passed | Mocked API/host |
| Artifact inventory | 404 files; d803281ee0e02f054427943f2155ecd875593e18ca8c8c6a8fda84da0c8ad058 | Exact source inventory, not runtime approval |
| CRLF recovery | Archive without Git passed; 395 text files converted/restored exactly across 404 entries; repeat check passed; real edit rejected without partial repair or manifest regeneration | Host simulation, not native Windows |
| Git whitespace | Passed | Source only |

No automated test framework/suite/dependency was added; review probes stayed outside the repository. Native PHP/MySQL runtime execution was unavailable. A real Chromium launch was attempted and failed at process-singleton socket creation with **Operation not permitted**; it exited 134. No real browser layout/keyboard/accessibility evidence is claimed. The environment interruption was recovered before delivery; no partial candidate was published as complete.

## Handover and integration

[Manual guide P6-13–P6-18](../manual-verification-phase-6-wp2.md), [pending native results](Phase_6_WP2_Manual_Results.csv), [developer contracts](Phase_6_WP2_Integration_Contracts.md) and [operations/release guidance](../operations/phase-06-wp2-owner-money.md) accompany this delivery. All six native result rows initially remain Not run. Native authority changes, exact money, authenticated HTTP/CSRF, concurrency, rollback and actual Windows/browser behavior require manual verification.

This branch is based on accepted WP1 candidate b7b4d7ad40afbe9b3023c9c0e67457ba0bee4081 (PR #45), which remained open when WP2 began; main was f985b5a88516ecd3861497bae0ee90984dfe47a1. It targets main directly and includes that accepted prerequisite and approved plan. It can be reviewed as a cumulative candidate; do not assume WP1 was merged or merge multiple stale branches blindly. No repository merge or deployment is performed here. WP2 acceptance must precede WP3 implementation.

## Subsequent error investigation — 13 November 2026

The Owner's native screenshot/log shows the migration applied and history returning ErrorException/500; WP2 remains pending acceptance. A [metadata label fix and diagnostic follow-up](Phase_6_WP2_Metadata_Error_Followup.md) explicitly aliases three uppercase-prone metadata columns and records safe source locations for future unexpected commercial errors. The 404-file WP2 inventory advances to `75b2902fbd74df5696caf29a8a7ebab007c3c7f63c1838aa24c9b82a73fd4524`; the initial hash above is historical. Actual old/new SQL-label fixture checks, 264 PHP grammar files and 38 host UI variants pass. Native retest must confirm resolution; no new migration or business data change is made.
